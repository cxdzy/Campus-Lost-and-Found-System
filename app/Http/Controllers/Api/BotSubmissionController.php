<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessVisionTagsJob;
use App\Models\Category;
use App\Models\Finder;
use App\Models\FoundItem;
use App\Models\Item;
use App\Models\Loser;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BotSubmissionController extends Controller
{
    private function authorizeBot(Request $request): ?JsonResponse
    {
        $secret = config('services.bot.secret');
        if ($secret && $request->header('X-Bot-Secret') !== $secret) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }
        return null;
    }

    public function store(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeBot($request)) {
            return $deny;
        }
        $validated = $request->validate([
            'image_url'        => ['required', 'url'],
            'caption'          => ['nullable', 'string', 'max:500'],
            'latitude'         => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'        => ['nullable', 'numeric', 'between:-180,180'],
            'telegram_chat_id' => ['nullable', 'string', 'max:255'],
            'category_id'      => ['nullable', 'integer'],
        ]);

        $download = Http::timeout(15)->get($validated['image_url']);

        if (!$download->successful() || !$download->body()) {
            return response()->json(['message' => 'Could not download image from provided URL.'], 422);
        }

        $imageContent = $download->body();
        $ext          = $this->detectExtension($download->header('Content-Type'), $imageContent);
        $filename     = Str::uuid() . '.' . $ext;
        Storage::disk('public')->put('items/' . $filename, $imageContent);
        $imagePath = 'items/' . $filename;

        $title = trim($validated['caption'] ?? '') ?: 'Item reported via Telegram';

        $category = (!empty($validated['category_id'])
                ? Category::find($validated['category_id'])
                : null)
            ?? Category::where('category_name', 'Others')->first()
            ?? Category::first()
            ?? Category::firstOrCreate(
                ['category_name' => 'Others'],
                ['icon_identifier' => 'others']
            );

        $lat = isset($validated['latitude'])  ? (float) $validated['latitude']  : 0.0;
        $lng = isset($validated['longitude']) ? (float) $validated['longitude'] : 0.0;

        DB::beginTransaction();
        try {
            $item = Item::create([
                'category_id'       => $category->id,
                'title_description' => $title,
                'latitude'          => $lat,
                'longitude'         => $lng,
                'location_name'     => ($lat !== 0.0 || $lng !== 0.0)
                                        ? "GPS: {$lat}, {$lng}"
                                        : 'Telegram Bot',
                'status'            => 'Pending',
            ]);

            // Resolve or auto-create a Finder profile for this Telegram account.
            // Pattern: Finder → User (TPT). We look up by telegram_chat_id on the
            // finders table first; if absent, we find-or-create the parent User then
            // create the Finder row so finder_id is never left null.
            $finderId = null;
            if (!empty($validated['telegram_chat_id'])) {
                $chatId = $validated['telegram_chat_id'];

                $finder = Finder::where('telegram_chat_id', $chatId)->first();

                if (!$finder) {
                    $botUser = User::firstOrCreate(
                        ['telegram_chat_id' => $chatId],
                        [
                            'name'     => 'Telegram #' . $chatId,
                            'role'     => 'User',
                            'password' => Str::random(32),
                        ]
                    );

                    $finder = Finder::firstOrCreate(
                        ['user_id'          => $botUser->id],
                        ['telegram_chat_id' => $chatId]
                    );
                }

                $finderId = $finder->user_id;
            }

            $foundItem = FoundItem::create([
                'item_id'    => $item->id,
                'finder_id'  => $finderId,
                'image_path' => $imagePath,
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to save item: ' . $e->getMessage()], 500);
        }

        // Only dispatch vision+matching once GPS is known.
        // When GPS is absent the bot will call update-location to supply it first.
        $hasGps = ($lat !== 0.0 || $lng !== 0.0);
        if ($hasGps) {
            ProcessVisionTagsJob::dispatch($foundItem->item_id);
        }

        return response()->json(['message' => 'Item saved successfully', 'id' => $item->id]);
    }

    public function updateLocation(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeBot($request)) {
            return $deny;
        }
        $validated = $request->validate([
            'found_item_id' => ['required', 'integer', 'exists:items,id'],
            'latitude'      => ['required', 'numeric', 'between:-90,90'],
            'longitude'     => ['required', 'numeric', 'between:-180,180'],
        ]);

        $item = Item::find($validated['found_item_id']);

        if (!$item || !$item->foundItem) {
            return response()->json(['message' => 'Found item not found.'], 404);
        }

        $lat = (float) $validated['latitude'];
        $lng = (float) $validated['longitude'];

        $item->update([
            'latitude'      => $lat,
            'longitude'     => $lng,
            'location_name' => "GPS: {$lat}, {$lng}",
        ]);

        ProcessVisionTagsJob::dispatch($item->id);

        return response()->json(['message' => 'Location updated and processing started.', 'id' => $item->id]);
    }

    public function linkAccount(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeBot($request)) {
            return $deny;
        }

        $validated = $request->validate([
            'matric_number'    => ['required', 'string', 'max:20'],
            'telegram_chat_id' => ['required', 'string', 'max:255'],
        ]);

        $loser = Loser::where('matric_number', $validated['matric_number'])
                      ->with('user')
                      ->first();

        if (!$loser) {
            return response()->json(['message' => 'No account found for that matric number.'], 404);
        }

        $loser->user->update(['telegram_chat_id' => $validated['telegram_chat_id']]);

        return response()->json(['message' => 'Telegram account linked successfully.']);
    }

    private function detectExtension(?string $contentType, string $body): string
    {
        if ($contentType) {
            if (Str::contains($contentType, ['jpeg', 'jpg'])) return 'jpg';
            if (Str::contains($contentType, 'png'))           return 'png';
            if (Str::contains($contentType, 'gif'))           return 'gif';
            if (Str::contains($contentType, 'webp'))          return 'webp';
        }
        $h = substr($body, 0, 12);
        if (substr($h, 0, 3) === "\xFF\xD8\xFF")                        return 'jpg';
        if (substr($h, 0, 8) === "\x89PNG\r\n\x1A\n")                   return 'png';
        if (substr($h, 0, 3) === 'GIF')                                  return 'gif';
        if (substr($h, 0, 4) === 'RIFF' && substr($h, 8, 4) === 'WEBP') return 'webp';
        return 'jpg';
    }
}
