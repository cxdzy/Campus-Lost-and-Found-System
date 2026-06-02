<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Item;
use Illuminate\Support\Facades\Storage;

class FixImageExtensions extends Command
{
    protected $signature = 'items:fix-extensions';

    protected $description = 'Rename local image files that are missing extensions and update DB paths';

    public function handle(): int
    {
        $items = Item::whereNotNull('image_path')
            ->where('image_path', 'not like', 'http%')
            ->get(['id', 'image_path']);

        $fixed = 0;

        foreach ($items as $item) {
            $path = $item->image_path;

            // Already has an extension
            if (pathinfo($path, PATHINFO_EXTENSION) !== '') {
                continue;
            }

            if (!Storage::disk('public')->exists($path)) {
                $this->warn("ID {$item->id}: file missing — {$path}");
                continue;
            }

            $body = Storage::disk('public')->get($path);
            $ext = $this->detectExtension($body);
            $newPath = $path . '.' . $ext;

            Storage::disk('public')->put($newPath, $body);
            Storage::disk('public')->delete($path);

            $item->image_path = $newPath;
            $item->save();

            $this->info("ID {$item->id}: {$path} → {$newPath}");
            $fixed++;
        }

        $this->info("Done. Fixed {$fixed} file(s).");
        return 0;
    }

    private function detectExtension(string $body): string
    {
        $h = substr($body, 0, 12);
        if (substr($h, 0, 3) === "\xFF\xD8\xFF") return 'jpg';
        if (substr($h, 0, 8) === "\x89PNG\r\n\x1A\n") return 'png';
        if (substr($h, 0, 3) === 'GIF') return 'gif';
        if (substr($h, 0, 4) === 'RIFF' && substr($h, 8, 4) === 'WEBP') return 'webp';
        return 'jpg';
    }
}
