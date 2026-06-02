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
            'caption' => ['required', 'string'],
        ]);

        $analysis = $vision->analyzeImage($validated['image_url']);

        $categoryName = strtolower($analysis['category'] ?? 'electronics');
        $category = Category::query()
            ->whereRaw('LOWER(category_name) = ?', [$categoryName])
            ->first();

        if (!$category) {
            $category = Category::query()->first();
        }

        if (!$category) {
            $category = Category::query()->firstOrCreate([
                'category_name' => 'Electronics',
            ], [
                'icon_identifier' => 'electronics',
            ]);
        }

        $user = User::query()->where('matric_number', 'ADMIN-001')->first();

        if (!$user) {
            $user = User::query()->firstOrCreate([
                'matric_number' => 'BOT-000',
            ], [
                'name' => 'Telegram Bot',
                'role' => 'Admin',
                'telegram_chat_id' => null,
                'password' => bcrypt('password123'),
            ]);
        }

        $title = trim(($analysis['description'] ?? '') . ' - ' . $validated['caption']);

        // Attempt to download the remote image into local storage for reliable serving.
        $imagePath = $validated['image_url'];

        try {
            $response = Http::timeout(10)->get($validated['image_url']);
            if ($response->successful() && $response->body()) {
                $ext = null;
                $contentType = $response->header('Content-Type');
                if ($contentType) {
                    if (Str::contains($contentType, 'jpeg')) $ext = 'jpg';
                    elseif (Str::contains($contentType, 'png')) $ext = 'png';
                    elseif (Str::contains($contentType, 'gif')) $ext = 'gif';
                }

                $filename = 'telegram_' . Str::random(12) . ($ext ? '.' . $ext : '');
                $stored = Storage::disk('public')->put('found_items/' . $filename, $response->body());
                if ($stored) {
                    $imagePath = 'found_items/' . $filename;
                }
            }
        } catch (\Throwable $e) {
            // If download fails, fall back to using remote URL as-is.
        }

        $item = Item::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'type' => 'Found',
            'title_description' => $title !== '-' ? $title : $validated['caption'],
            'latitude' => 0.0,
            'longitude' => 0.0,
            'location_name' => 'Telegram Bot',
            'image_path' => $imagePath,
            'status' => 'Pending',
        ]);

        return response()->json([
            'message' => 'Bot submission saved',
            'id' => $item->id,
            'analysis' => $analysis,
        ]);
    }
}
