<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Item;
use Illuminate\Http\Request;
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
}
