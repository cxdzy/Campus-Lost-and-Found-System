<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VisionAiService
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent';

    private const PROMPT = <<<'PROMPT'
Analyze this image of a found item at a university campus.
Return ONLY a valid JSON object — no markdown, no explanation — with exactly these three fields:
{
  "category": "<one of: Electronics, Accessories, Keys, Wallets, IDs, Bags & Backpacks, Clothing, Other>",
  "confidence": <float between 0.0 and 1.0>,
  "description": "<concise item title, max 8 words>"
}
PROMPT;

    /**
     * Analyze an already-downloaded image body.
     *
     * @param  string  $imageBody  Raw binary content of the image
     * @param  string  $mimeType   MIME type (e.g. image/jpeg)
     * @return array{category: string, confidence: float, description: string}
     */
    public function analyzeImage(string $imageBody, string $mimeType = 'image/jpeg'): array
    {
        $apiKey = config('services.gemini.key');

        if (!$apiKey) {
            Log::warning('VisionAiService: GEMINI_API_KEY not set, returning fallback.');
            return $this->fallback();
        }

        try {
            $response = Http::timeout(30)->post(self::ENDPOINT . '?key=' . $apiKey, [
                'contents' => [[
                    'parts' => [
                        [
                            'inline_data' => [
                                'mime_type' => $mimeType,
                                'data'      => base64_encode($imageBody),
                            ],
                        ],
                        ['text' => self::PROMPT],
                    ],
                ]],
                'generationConfig' => [
                    'response_mime_type' => 'application/json',
                    'temperature'        => 0.1,
                ],
            ]);

            if (!$response->successful()) {
                Log::error('Gemini API error', ['status' => $response->status(), 'body' => $response->body()]);
                return $this->fallback();
            }

            $text = $response->json('candidates.0.content.parts.0.text') ?? '';

            return $this->parseResult($text);
        } catch (\Throwable $e) {
            Log::error('VisionAiService exception: ' . $e->getMessage());
            return $this->fallback();
        }
    }

    private function parseResult(string $text): array
    {
        // Strip any accidental markdown fences Gemini might still add
        $text = preg_replace('/```(?:json)?\s*|\s*```/', '', trim($text));

        $data = json_decode($text, true);

        if (!is_array($data)) {
            Log::warning('VisionAiService: could not parse Gemini response', ['text' => $text]);
            return $this->fallback();
        }

        return [
            'category'    => (string)  ($data['category']    ?? 'Other'),
            'confidence'  => (float)   ($data['confidence']  ?? 0.5),
            'description' => (string)  ($data['description'] ?? 'Found item'),
        ];
    }

    private function fallback(): array
    {
        return [
            'category'   => 'Other',
            'confidence' => 0.5,
            'description' => 'Found item',
        ];
    }
}
