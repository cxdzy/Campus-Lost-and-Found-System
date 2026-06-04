<?php

namespace App\Services;

use App\Models\AiTag;
use App\Models\ApiLog;
use App\Models\FoundItem;

class MockCloudVisionService
{
    private const TAG_POOL = [
        'wallet', 'black', 'leather', 'keys', 'metal', 'silver', 'bag', 'blue',
        'backpack', 'phone', 'device', 'screen', 'notebook', 'pen', 'red',
        'glasses', 'frame', 'card', 'id-card', 'lanyard', 'yellow', 'small',
        'bottle', 'plastic', 'umbrella', 'fabric', 'brown', 'zipper', 'strap',
        'watch', 'digital', 'charger', 'cable', 'white', 'earphones', 'case',
    ];

    /**
     * Generate mock AI vision tags for a found item and persist them along
     * with a structured API log entry for administrative audit tracing.
     */
    public function analyse(FoundItem $foundItem): array
    {
        // Pick 3–5 unique random tags from the pool
        $pool  = self::TAG_POOL;
        shuffle($pool);
        $count = random_int(3, 5);
        $tags  = array_slice($pool, 0, $count);

        $tagRecords = [];
        foreach ($tags as $tagName) {
            $confidence = round(random_int(7500, 9800) / 10000, 4); // 0.75 – 0.98
            $tagRecords[] = AiTag::create([
                'found_item_id'    => $foundItem->item_id,
                'tag_name'         => $tagName,
                'confidence_level' => $confidence,
            ]);
        }

        $payload = [
            'responses' => [[
                'labelAnnotations' => array_map(fn ($t) => [
                    'description' => $t->tag_name,
                    'score'       => $t->confidence_level,
                ], $tagRecords),
            ]],
        ];

        ApiLog::create([
            'item_id'          => $foundItem->item_id,
            'service'          => 'CloudVisionAPI',
            'http_status_code' => 200,
            'payload_response' => json_encode($payload),
            'logged_at'        => now(),
        ]);

        return $tagRecords;
    }
}
