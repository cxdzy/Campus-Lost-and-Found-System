<?php

namespace App\Services;

use App\Models\AiTag;
use App\Models\ApiLog;
use App\Models\FoundItem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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

        $imageContent = $this->readImageAsBase64($foundItem->image_path);

        $requestBody = [
            'requests' => [[
                'image'    => ['content' => $imageContent],
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

    private function readImageAsBase64(string $path): string
    {
        // Absolute URL — fetch remotely
        if (Str::startsWith($path, ['http://', 'https://'])) {
            $bytes = Http::withoutVerifying()->timeout(15)->get($path)->body();
            return base64_encode($bytes);
        }

        // Relative storage path — read directly from disk
        $bytes = Storage::disk('public')->get($path);

        if ($bytes === null) {
            throw new \RuntimeException("Image not found on storage disk: {$path}");
        }

        return base64_encode($bytes);
    }
}
