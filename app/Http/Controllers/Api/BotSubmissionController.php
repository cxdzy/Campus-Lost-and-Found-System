<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Item;
use App\Models\User;
use App\Services\VisionAiService;
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

        $item = Item::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'type' => 'Found',
            'title_description' => $title !== '-' ? $title : $validated['caption'],
            'latitude' => 0.0,
            'longitude' => 0.0,
            'location_name' => 'Telegram Bot',
            'image_path' => $validated['image_url'],
            'status' => 'Pending',
        ]);

        return response()->json([
            'message' => 'Bot submission saved',
            'id' => $item->id,
            'analysis' => $analysis,
        ]);
    }
}
