<?php

namespace App\Services;

use App\Models\ApiLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    private string $baseUrl;

    public function __construct()
    {
        $token = config('services.telegram.bot_token', '');
        $this->baseUrl = "https://api.telegram.org/bot{$token}";
    }

    public function sendMessage(string $chatId, string $text, ?int $itemId = null, bool $redactPayload = false): void
    {
        $payload = [
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => 'HTML',
        ];

        try {
            $response = Http::timeout(10)->post("{$this->baseUrl}/sendMessage", $payload);

            ApiLog::create([
                'item_id'          => $itemId,
                'service'          => 'Telegram',
                'http_status_code' => $response->status(),
                'payload_response' => $redactPayload ? '[REDACTED]' : $response->body(),
                'logged_at'        => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('TelegramService::sendMessage failed: ' . $e->getMessage());

            ApiLog::create([
                'item_id'          => $itemId,
                'service'          => 'Telegram',
                'http_status_code' => 0,
                'payload_response' => '[DELIVERY FAILURE]',
                'logged_at'        => now(),
            ]);
        }
    }
}
