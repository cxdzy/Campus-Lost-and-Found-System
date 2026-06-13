<?php

namespace App\Services;

use App\Models\AiTag;
use App\Models\ApiLog;
use App\Models\FoundItem;
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

        $body = json_encode([
            'requests' => [[
                'image'    => ['content' => $imageContent],
                'features' => [['type' => 'LABEL_DETECTION', 'maxResults' => 10]],
            ]],
        ]);

        try {
            [$statusCode, $responseBody] = $this->curlPost(self::ENDPOINT . '?key=' . $apiKey, $body);

            $decoded = json_decode($responseBody, true);
            $labels  = $decoded['responses'][0]['labelAnnotations'] ?? [];

            ApiLog::create([
                'item_id'          => $foundItem->item_id,
                'service'          => 'CloudVisionAPI',
                'http_status_code' => $statusCode,
                'payload_response' => json_encode(['labelAnnotations' => $labels]),
                'logged_at'        => now(),
            ]);

            if ($statusCode < 200 || $statusCode >= 300) {
                throw new \RuntimeException("Google Vision API error {$statusCode}: {$responseBody}");
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

    private function curlPost(string $url, string $jsonBody): array
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $jsonBody,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        ]);

        $responseBody = curl_exec($ch);
        $statusCode   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError    = curl_error($ch);
        curl_close($ch);

        if ($responseBody === false) {
            throw new \RuntimeException("cURL request failed: {$curlError}");
        }

        return [$statusCode, $responseBody];
    }

    private function readImageAsBase64(string $path): string
    {
        if (Str::startsWith($path, ['http://', 'https://'])) {
            [$status, $bytes] = $this->curlGet($path);
            return base64_encode($bytes);
        }

        $bytes = Storage::disk('public')->get($path);

        if ($bytes === null) {
            throw new \RuntimeException("Image not found on storage disk: {$path}");
        }

        return base64_encode($bytes);
    }

    private function curlGet(string $url): array
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        ]);

        $body      = curl_exec($ch);
        $status    = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new \RuntimeException("cURL GET failed: {$curlError}");
        }

        return [$status, $body];
    }
}
