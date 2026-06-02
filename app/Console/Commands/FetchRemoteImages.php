<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Item;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class FetchRemoteImages extends Command
{
    protected $signature = 'items:fetch-remote-images {--limit=100 : Max items to process at once}';

    protected $description = 'Download remote image URLs for items and store them on the public disk';

    public function handle()
    {
        $limit = (int) $this->option('limit');

        $query = Item::query()
            ->whereNotNull('image_path')
            ->where('image_path', 'like', 'http%')
            ->limit($limit);

        $items = $query->get();

        if ($items->isEmpty()) {
            $this->info('No remote images found to fetch.');
            return 0;
        }

        foreach ($items as $item) {
            $this->info("Processing item {$item->id} -> {$item->image_path}");
            try {
                $response = Http::timeout(10)->get($item->image_path);
                if ($response->successful() && $response->body()) {
                    $body = $response->body();
                    $ext = $this->detectImageExtension($response->header('Content-Type'), $body);
                    $filename = 'telegram_' . Str::random(12) . '.' . $ext;
                    $path = 'found_items/' . $filename;
                    $stored = Storage::disk('public')->put($path, $body);
                    if ($stored) {
                        $item->image_path = $path;
                        $item->save();
                        $this->info("Saved to {$path}");
                    } else {
                        $this->error('Failed to store file');
                    }
                } else {
                    $this->warn('Failed to download or empty body');
                }
            } catch (\Throwable $e) {
                $this->error('Error: ' . $e->getMessage());
            }
        }

        $this->info('Done.');

        return 0;
    }

    private function detectImageExtension(?string $contentType, string $body): string
    {
        if ($contentType) {
            if (Str::contains($contentType, 'jpeg') || Str::contains($contentType, 'jpg')) return 'jpg';
            if (Str::contains($contentType, 'png')) return 'png';
            if (Str::contains($contentType, 'gif')) return 'gif';
            if (Str::contains($contentType, 'webp')) return 'webp';
        }
        $header = substr($body, 0, 12);
        if (substr($header, 0, 3) === "\xFF\xD8\xFF") return 'jpg';
        if (substr($header, 0, 8) === "\x89PNG\r\n\x1A\n") return 'png';
        if (substr($header, 0, 3) === 'GIF') return 'gif';
        if (substr($header, 0, 4) === 'RIFF' && substr($header, 8, 4) === 'WEBP') return 'webp';
        return 'jpg';
    }
}
