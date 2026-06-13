<?php

namespace App\Services;

use App\Models\AiTag;
use App\Models\ApiLog;
use App\Models\FoundItem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class VisionService
{
    private const ENDPOINT = 'https://vision.googleapis.com/v1/images:annotate';

    public function analyse(FoundItem $foundItem): array
    {
        $apiKey = config('services.google.vision_api_key');

        if (!$apiKey) {
            return app(MockCloudVisionService::class)->analyse($foundItem);
        }

        $imageUrl = $this->resolveImageUrl($foundItem->image_path);

        $requestBody = [
            'requests' => [[
                'image'    => ['source' => ['imageUri' => $imageUrl]],
                'features' => [['type' => 'LABEL_DETECTION', 'maxResults' => 10]],
            ]],
        ];

        try {
            $response   = Http::withoutVerifying()->timeout(15)->post(self::ENDPOINT . '?key=' . $apiKey, $requestBody);
            $statusCode = $response->status();
            $labels     = $response->json('responses.0.labelAnnotations') ?? [];

            ApiLog::create([
                'item_id'          => $foundItem->item_id,
                'service'          => 'CloudVisionAPI',
                'http_status_code' => $statusCode,
                'payload_response' => json_encode(['labelAnnotations' => $labels]),
                'logged_at'        => now(),
            ]);

            if (!$response->successful()) {
                throw new \RuntimeException("Google Vision API error {$statusCode}: " . $response->body());
            }
        } catch (\Throwable $e) {
            Log::error('VisionService::analyse failed for item ' . $foundItem->item_id . ': ' . $e->getMessage());

            ApiLog::create([
                'item_id'          => $foundItem->item_id,
                'service'          => 'CloudVisionAPI',
                'http_status_code' => 0,
                'payload_response' => '[DELIVERY FAILURE]',
                'logged_at'        => now(),
            ]);

            throw $e;
        }

        $tagRecords = [];
        foreach ($labels as $label) {
            $tagRecords[] = AiTag::create([
                'found_item_id'    => $foundItem->item_id,
                'tag_name'         => strtolower($label['description']),
                'confidence_level' => round((float) $label['score'], 4),
            ]);
        }

        return $tagRecords;
    }

    private function resolveImageUrl(string $path): string
    {
        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        // Prefer STORAGE_PUBLIC_URL (set in Dokploy to the public domain).
        // Fall back to APP_URL only as a last resort — it may be localhost in some setups.
        $base = config('services.storage.public_url')
            ?? config('app.url');

        return rtrim($base, '/') . '/storage/' . ltrim($path, '/');
    }
}
