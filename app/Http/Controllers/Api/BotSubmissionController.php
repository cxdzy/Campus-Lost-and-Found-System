<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Finder;
use App\Models\FoundItem;
use App\Models\Item;
use App\Services\MatchingService;
use App\Services\MockCloudVisionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BotSubmissionController extends Controller
{
    public function __construct(
        private MockCloudVisionService $visionService,
        private MatchingService        $matchingService,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'image_url'        => ['required', 'url'],
            'caption'          => ['nullable', 'string', 'max:500'],
            'latitude'         => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'        => ['nullable', 'numeric', 'between:-180,180'],
            'telegram_chat_id' => ['nullable', 'string', 'max:255'],
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

        $category = Category::query()->where('category_name', 'Others')->first()
            ?? Category::query()->first()
            ?? Category::query()->firstOrCreate(
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

            // Resolve or create a Finder record for the Telegram user
            $finderId = null;
            if (!empty($validated['telegram_chat_id'])) {
                $finder = Finder::firstOrCreate(
                    ['telegram_chat_id' => $validated['telegram_chat_id']],
                    ['user_id' => null],
                );
                // A Telegram-only finder may not have a users record; skip FK if null
                if ($finder->user_id) {
                    $finderId = $finder->user_id;
                }
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

        // Run mock vision tagging and matching outside the transaction
        $this->visionService->analyse($foundItem);
        $this->matchingService->matchFoundItem($foundItem);

        return response()->json(['message' => 'Item saved successfully', 'id' => $item->id]);
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
