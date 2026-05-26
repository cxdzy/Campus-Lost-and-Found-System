<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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

        return response()->json($analysis);
    }
}
