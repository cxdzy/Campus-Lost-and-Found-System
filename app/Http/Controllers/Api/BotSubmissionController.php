<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Item;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BotSubmissionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'image_url' => ['required', 'url'],
            'caption'   => ['nullable', 'string', 'max:500'],
        ]);

        // Download the image from the Telegram URL and save locally so we never
        // hotlink to Telegram's CDN (avoids ERR_BLOCKED_BY_ORB and hides the bot token).
        $download = Http::timeout(15)->get($validated['image_url']);

        if (!$download->successful() || !$download->body()) {
            return response()->json(['message' => 'Could not download image from provided URL.'], 422);
        }

        $imageContent = $download->body();
        $ext          = $this->detectExtension($download->header('Content-Type'), $imageContent);
        $filename     = Str::uuid() . '.' . $ext;

        Storage::disk('public')->put('items/' . $filename, $imageContent);

        // Store only the relative path — the backend converts it to a full URL on read.
        $imagePath = 'items/' . $filename;

        $title = trim($validated['caption'] ?? '') ?: 'Item reported via Telegram';

        $category = Category::query()->where('category_name', 'Others')->first()
            ?? Category::query()->first()
            ?? Category::query()->firstOrCreate(
                ['category_name' => 'Others'],
                ['icon_identifier' => 'others']
            );

        $user = User::query()->where('matric_number', 'ADMIN-001')->first()
            ?? User::query()->where('role', 'Admin')->first();

        if (!$user) {
            return response()->json(['message' => 'No admin user found to assign submission to.'], 500);
        }

        $item = Item::create([
            'user_id'           => $user->id,
            'category_id'       => $category->id,
            'type'              => 'Found',
            'title_description' => $title,
            'latitude'          => 0.0,
            'longitude'         => 0.0,
            'location_name'     => 'Telegram Bot',
            'image_path'        => $imagePath,
            'status'            => 'Pending',
        ]);

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
