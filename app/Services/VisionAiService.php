<?php

namespace App\Services;

class VisionAiService
{
    public function analyzeImage(string $imageUrl): array
    {
        return [
            'category' => 'electronics',
            'confidence' => 0.95,
            'description' => 'A black smartphone',
        ];
    }
}
