<?php

namespace App\Services;

use App\Models\AiTag;
use App\Models\FoundItem;
use App\Models\Item;
use App\Models\LostItem;
use App\Models\MatchAlert;

class MatchingService
{
    private const MAX_DISTANCE_KM = 10.0;

    public function __construct(private TelegramService $telegram) {}

    /**
     * Run matching after a found item is submitted.
     * Compares against all Pending lost items.
     */
    public function matchFoundItem(FoundItem $foundItem): void
    {
        $foundItemBase = $foundItem->item()->with('category')->first();
        $foundTags     = AiTag::where('found_item_id', $foundItem->item_id)
                              ->pluck('tag_name')
                              ->map(fn ($t) => strtolower($t))
                              ->toArray();

        $lostItems = LostItem::with(['item.category'])->get();

        foreach ($lostItems as $lostItem) {
            $lostBase = $lostItem->item;
            if ($lostBase->status !== 'Pending') {
                continue;
            }
            if (MatchAlert::where('lost_item_id', $lostItem->item_id)
                          ->where('found_item_id', $foundItem->item_id)
                          ->exists()) {
                continue;
            }

            $score = $this->computeScore($foundTags, $foundItemBase, $lostBase);

            if ($score >= (float) config('matching.threshold', 0.80)) {
                $alert = MatchAlert::create([
                    'lost_item_id'  => $lostItem->item_id,
                    'found_item_id' => $foundItem->item_id,
                    'match_score'   => round($score, 4),
                    'is_notified'   => true,
                ]);

                $this->updateItemStatus($foundItemBase, $lostBase);
                $this->dispatchTelegramNotification($alert, $lostItem, $foundItem);
            }
        }
    }

    /**
     * Run matching after a lost item is submitted.
     * Compares against all Pending found items.
     */
    public function matchLostItem(LostItem $lostItem): void
    {
        $lostBase  = $lostItem->item()->with('category')->first();
        $foundItems = FoundItem::with(['item.category', 'aiTags'])->get();

        foreach ($foundItems as $foundItem) {
            $foundBase = $foundItem->item;
            if ($foundBase->status !== 'Pending') {
                continue;
            }
            if (MatchAlert::where('lost_item_id', $lostItem->item_id)
                          ->where('found_item_id', $foundItem->item_id)
                          ->exists()) {
                continue;
            }

            $foundTags = $foundItem->aiTags
                             ->pluck('tag_name')
                             ->map(fn ($t) => strtolower($t))
                             ->toArray();

            $score = $this->computeScore($foundTags, $foundBase, $lostBase);

            if ($score >= (float) config('matching.threshold', 0.80)) {
                $alert = MatchAlert::create([
                    'lost_item_id'  => $lostItem->item_id,
                    'found_item_id' => $foundItem->item_id,
                    'match_score'   => round($score, 4),
                    'is_notified'   => true,
                ]);

                $this->updateItemStatus($foundBase, $lostBase);
                $this->dispatchTelegramNotification($alert, $lostItem, $foundItem);
            }
        }
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function computeScore(array $foundTags, Item $foundBase, Item $lostBase): float
    {
        $tagScore       = $this->tagOverlapScore($foundTags, $lostBase->title_description);
        $proximityScore = $this->proximityScore(
            $foundBase->latitude, $foundBase->longitude,
            $lostBase->latitude,  $lostBase->longitude,
        );

        return 0.60 * $tagScore + 0.40 * $proximityScore;
    }

    private const STOPWORDS = ['the', 'and', 'was', 'for', 'my', 'its', 'with', 'has', 'not', 'are', 'but', 'this', 'that', 'from', 'have'];

    private function tagOverlapScore(array $foundTags, string $lostDescription): float
    {
        if (empty($foundTags)) {
            return 0.0;
        }

        $desc = strtolower($lostDescription);

        // Extract significant words from the description for bidirectional matching.
        // Skip words shorter than 3 chars and common stopwords to avoid spurious hits.
        $descWords = array_values(array_filter(
            preg_split('/\W+/', $desc, -1, PREG_SPLIT_NO_EMPTY),
            fn (string $w) => strlen($w) >= 3 && !in_array($w, self::STOPWORDS, true),
        ));

        $hits = 0;

        foreach ($foundTags as $tag) {
            // Direction 1 (original): full tag phrase appears in the description.
            if (str_contains($desc, $tag)) {
                $hits++;
                continue;
            }

            // Direction 2 (bidirectional): any significant description word appears in the tag.
            // e.g. description "phone" matches tag "mobile phone".
            foreach ($descWords as $word) {
                if (str_contains($tag, $word)) {
                    $hits++;
                    break;
                }
            }
        }

        return $hits / count($foundTags);
    }

    private function proximityScore(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $distKm = $this->haversineKm($lat1, $lon1, $lat2, $lon2);

        if ($distKm >= self::MAX_DISTANCE_KM) {
            return 0.0;
        }

        // Linear decay: 0 km → 1.0 score, MAX_DISTANCE_KM → 0.0 score
        return 1.0 - ($distKm / self::MAX_DISTANCE_KM);
    }

    private function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function updateItemStatus(Item $foundBase, Item $lostBase): void
    {
        $foundBase->update(['status' => 'Matched']);
        $lostBase->update(['status' => 'Matched']);
    }

    private function dispatchTelegramNotification(
        MatchAlert $alert,
        LostItem   $lostItem,
        FoundItem  $foundItem,
    ): void {
        $loser = $lostItem->loser()->with('user')->first();
        if (!$loser?->user?->telegram_chat_id) {
            return;
        }

        $score   = round($alert->match_score * 100, 1);
        $message = "🔍 <b>Match Alert — {$score}% confidence</b>\n\n"
                 . "Your lost item <b>{$lostItem->item->title_description}</b> "
                 . "may match a found item: <b>{$foundItem->item->title_description}</b>\n"
                 . "📍 Found at: {$foundItem->item->location_name}\n\n"
                 . "Visit the Campus Lost &amp; Found portal to generate your claim OTP.";

        $this->telegram->sendMessage(
            $loser->user->telegram_chat_id,
            $message,
            $foundItem->item_id,
        );
    }
}
