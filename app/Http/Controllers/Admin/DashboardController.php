<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiTag;
use App\Models\ApiLog;
use App\Models\Item;
use App\Models\MatchAlert;
use App\Models\ReownershipClaim;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $items = Item::with(['category', 'foundItem.aiTags', 'matchAlertsAsFound'])
            ->has('foundItem')
            ->latest()
            ->get()
            ->map(function (Item $item) {
                $image = $item->foundItem?->image_path;

                if (!$image) {
                    $image = null;
                } elseif (!Str::startsWith($image, ['http://', 'https://'])) {
                    $image = Storage::disk('public')->exists($image)
                        ? '/storage/' . $image
                        : '/storage/' . $image;
                }

                $tags       = $item->foundItem?->aiTags ?? collect();
                $confidence = $tags->isNotEmpty()
                    ? round($tags->avg('confidence_level') * 100, 1)
                    : 0;

                $bestAlert = $item->matchAlertsAsFound->sortByDesc('match_score')->first();

                return [
                    'id'                => $item->id,
                    'title_description' => $item->title_description,
                    'category'          => $item->category?->category_name,
                    'location_name'     => $item->location_name,
                    'latitude'          => $item->latitude,
                    'longitude'         => $item->longitude,
                    'status'            => $item->status,
                    'image_url'         => $image,
                    'confidence'        => $confidence,
                    'tags'              => $tags->pluck('tag_name')->all(),
                    'match_alert_id'    => $bestAlert?->id,
                    'match_score'       => $bestAlert ? round($bestAlert->match_score * 100, 1) : null,
                ];
            })
            ->all();

        return Inertia::render('Admin/AdminDashboard', [
            'items' => $items,
        ]);
    }

    public function destroy(Item $item): JsonResponse
    {
        // Only found items appear in the admin inventory
        if (!$item->foundItem) {
            return response()->json(['error' => 'Item not found in inventory.'], 404);
        }

        $imagePath = $item->foundItem->image_path;

        DB::beginTransaction();
        try {
            AiTag::where('found_item_id', $item->id)->delete();
            ReownershipClaim::where('found_item_id', $item->id)->delete();
            MatchAlert::where('found_item_id', $item->id)->delete();
            ApiLog::where('item_id', $item->id)->delete();
            $item->foundItem->delete();
            $item->delete();
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }

        // Remove image file after commit so a rollback keeps the file intact
        if ($imagePath && !Str::startsWith($imagePath, ['http://', 'https://'])) {
            Storage::disk('public')->delete($imagePath);
        }

        return response()->json(['message' => 'Item deleted.'], 200);
    }
}
