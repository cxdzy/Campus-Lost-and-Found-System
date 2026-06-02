<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Item;
use App\Models\User;
use App\Services\VisionAiService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BotSubmissionController extends Controller
{
    public function store(Request $request, VisionAiService $vision): JsonResponse
    {
        $validated = $request->validate([
            'image_url' => ['required', 'url'],
            'caption'   => ['required', 'string'],
        ]);

        // Download the image once — reused for both Gemini analysis and local storage.
        $imageBody = null;
        $mimeType  = 'image/jpeg';
        $imagePath = $validated['image_url']; // fallback: keep remote URL

        try {
            $download = Http::timeout(15)->get($validated['image_url']);

            if ($download->successful() && $download->body()) {
                $imageBody = $download->body();
                $mimeType  = $this->resolveMime($download->header('Content-Type'), $imageBody);

                // Persist to local public storage
                $ext      = $this->mimeToExt($mimeType);
                $filename = 'telegram_' . Str::random(12) . '.' . $ext;
                if (Storage::disk('public')->put('found_items/' . $filename, $imageBody)) {
                    $imagePath = 'found_items/' . $filename;
                }
            }
        } catch (\Throwable $e) {
            // Image download failed; continue without local copy.
        }

        // Run Gemini vision analysis on the downloaded body (or fall back to dummy).
        $analysis = $imageBody
            ? $vision->analyzeImage($imageBody, $mimeType)
            : ['category' => 'Other', 'confidence' => 0.5, 'description' => 'Found item'];

        // Resolve category row — match by name, fall back to first, or create Electronics.
        $categoryName = strtolower($analysis['category'] ?? 'other');
        $category = Category::query()->whereRaw('LOWER(category_name) = ?', [$categoryName])->first()
            ?? Category::query()->first()
            ?? Category::query()->firstOrCreate(
                ['category_name' => 'Electronics'],
                ['icon_identifier' => 'electronics']
            );

        // Resolve bot user row.
        $user = User::query()->where('matric_number', 'ADMIN-001')->first()
            ?? User::query()->firstOrCreate(
                ['matric_number' => 'BOT-000'],
                [
                    'name'             => 'Telegram Bot',
                    'role'             => 'Admin',
                    'telegram_chat_id' => null,
                    'password'         => bcrypt('bot-password'),
                ]
            );

        $rawTitle = trim(($analysis['description'] ?? '') . ' - ' . $validated['caption']);
        $title    = ($rawTitle === '-' || $rawTitle === '') ? $validated['caption'] : $rawTitle;

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

        return response()->json([
            'message'  => 'Bot submission saved',
            'id'       => $item->id,
            'analysis' => $analysis,
        ]);
    }

    private function resolveMime(?string $contentType, string $body): string
    {
        if ($contentType) {
            if (Str::contains($contentType, 'jpeg') || Str::contains($contentType, 'jpg')) return 'image/jpeg';
            if (Str::contains($contentType, 'png'))  return 'image/png';
            if (Str::contains($contentType, 'gif'))  return 'image/gif';
            if (Str::contains($contentType, 'webp')) return 'image/webp';
        }
        // Magic bytes fallback
        $h = substr($body, 0, 12);
        if (substr($h, 0, 3) === "\xFF\xD8\xFF")                                     return 'image/jpeg';
        if (substr($h, 0, 8) === "\x89PNG\r\n\x1A\n")                                return 'image/png';
        if (substr($h, 0, 3) === 'GIF')                                               return 'image/gif';
        if (substr($h, 0, 4) === 'RIFF' && substr($h, 8, 4) === 'WEBP')              return 'image/webp';
        return 'image/jpeg';
    }

    private function mimeToExt(string $mime): string
    {
        return match ($mime) {
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
            default      => 'jpg',
        };
    }
}
