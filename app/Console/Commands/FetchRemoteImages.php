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
                    $contentType = $response->header('Content-Type');
                    $ext = null;
                    if ($contentType) {
                        if (Str::contains($contentType, 'jpeg')) $ext = 'jpg';
                        elseif (Str::contains($contentType, 'png')) $ext = 'png';
                        elseif (Str::contains($contentType, 'gif')) $ext = 'gif';
                    }

                    $filename = 'telegram_' . Str::random(12) . ($ext ? '.' . $ext : '');
                    $path = 'found_items/' . $filename;
                    $stored = Storage::disk('public')->put($path, $response->body());
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
}
