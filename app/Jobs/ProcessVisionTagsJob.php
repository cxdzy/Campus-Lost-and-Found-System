<?php

namespace App\Jobs;

use App\Models\FoundItem;
use App\Services\MatchingService;
use App\Services\MockCloudVisionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessVisionTagsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $foundItemId) {}

    public function handle(MockCloudVisionService $vision, MatchingService $matching): void
    {
        $foundItem = FoundItem::find($this->foundItemId);

        if (!$foundItem) {
            Log::warning("ProcessVisionTagsJob: FoundItem {$this->foundItemId} not found — skipping.");
            return;
        }

        try {
            $vision->analyse($foundItem);
        } catch (\Throwable $e) {
            Log::error("ProcessVisionTagsJob: vision analysis failed for item {$this->foundItemId}: " . $e->getMessage());
            throw $e;
        }

        try {
            $matching->matchFoundItem($foundItem);
        } catch (\Throwable $e) {
            Log::error("ProcessVisionTagsJob: matching failed for item {$this->foundItemId}: " . $e->getMessage());
            throw $e;
        }
    }
}
