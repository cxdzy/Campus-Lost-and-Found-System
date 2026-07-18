<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreReportRequest;
use App\Http\Requests\Admin\UpdateReportRequest;
use App\Models\ApiLog;
use App\Models\Finder;
use App\Models\FoundItem;
use App\Models\Item;
use App\Models\Loser;
use App\Models\LostItem;
use App\Models\MatchAlert;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ReportsController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Item::class);

        $items = Item::query()
            ->with(['category', 'foundItem.finder.user', 'lostItem.loser.user'])
            ->latest()
            ->limit(200)
            ->get()
            ->map(function (Item $item) {
                $isFound = $item->foundItem !== null;
                $isLost  = $item->lostItem  !== null;

                $reporterName   = null;
                $reporterMatric = null;

                if ($isFound && $item->foundItem->finder?->user) {
                    $reporterName = $item->foundItem->finder->user->name;
                } elseif ($isLost && $item->lostItem->loser?->user) {
                    $reporterName   = $item->lostItem->loser->user->name;
                    $reporterMatric = $item->lostItem->loser->matric_number ?? null;
                }

                return array_merge($item->toArray(), [
                    'type'            => $isFound ? 'Found' : ($isLost ? 'Lost' : null),
                    'category_name'   => $item->category?->category_name,
                    'reporter_name'   => $reporterName,
                    'reporter_matric' => $reporterMatric,
                ]);
            });

        if ($request->wantsJson()) {
            return response()->json(['data' => $items]);
        }

        return Inertia::render('Admin/Reports', [
            'reports' => $items,
        ]);
    }

    public function show(Item $report)
    {
        $this->authorize('view', $report);

        $report->load(['category', 'foundItem.finder.user', 'lostItem.loser.user']);

        $isFound = $report->foundItem !== null;
        $isLost  = $report->lostItem  !== null;

        $rawImage = $isFound
            ? ($report->foundItem->image_path ?? null)
            : ($report->lostItem?->image_path ?? null);

        if (!$rawImage) {
            $imageUrl = null;
        } elseif (str_starts_with($rawImage, 'http')) {
            $imageUrl = $rawImage;
        } else {
            $imageUrl = Storage::url($rawImage);
        }

        $reporterName   = null;
        $reporterMatric = null;

        if ($isFound && $report->foundItem->finder?->user) {
            $reporterName = $report->foundItem->finder->user->name;
        } elseif ($isLost && $report->lostItem->loser?->user) {
            $reporterName   = $report->lostItem->loser->user->name;
            $reporterMatric = $report->lostItem->loser->matric_number ?? null;
        }

        return response()->json([
            'data' => array_merge($report->toArray(), [
                'type'            => $isFound ? 'Found' : ($isLost ? 'Lost' : null),
                'category_name'   => $report->category?->category_name,
                'image_url'       => $imageUrl,
                'reporter_name'   => $reporterName,
                'reporter_matric' => $reporterMatric,
            ]),
        ]);
    }

    public function store(StoreReportRequest $request)
    {
        $data = $request->validated();

        $imagePath = null;
        if ($request->hasFile('image_file')) {
            $imagePath = $request->file('image_file')->store('lost_placeholders', 'public');
        } elseif (!empty($data['image_path'])) {
            $imagePath = $data['image_path'];
        }

        $reporter = User::findOrFail($data['user_id']);

        DB::beginTransaction();
        try {
            $item = Item::create([
                'category_id'       => $data['category_id'],
                'title_description' => $data['title_description'],
                'latitude'          => $data['latitude'],
                'longitude'         => $data['longitude'],
                'location_name'     => $data['location_name'],
                'status'            => $data['status'],
            ]);

            if ($data['type'] === 'Lost') {
                $loser = Loser::firstOrCreate(
                    ['user_id' => $reporter->id],
                    ['matric_number' => $reporter->matric_number ?? ('STUDENT-' . $reporter->id)]
                );

                LostItem::create([
                    'item_id'    => $item->id,
                    'loser_id'   => $loser->user_id,
                    'image_path' => $imagePath,
                ]);
            } else {
                $finder = Finder::firstOrCreate(['user_id' => $reporter->id]);

                FoundItem::create([
                    'item_id'    => $item->id,
                    'finder_id'  => $finder->user_id,
                    'image_path' => $imagePath,
                ]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to save report: ' . $e->getMessage()], 500);
        }

        $item->load(['category', 'foundItem', 'lostItem']);
        $item->image_url = $imagePath ? Storage::url($imagePath) : null;

        return response()->json(['data' => $item], 201);
    }

    public function update(UpdateReportRequest $request, Item $report)
    {
        $data = $request->validated();

        $imagePath = null;
        if ($request->hasFile('image_file')) {
            $imagePath = $request->file('image_file')->store('lost_placeholders', 'public');
        } elseif (!empty($data['image_path'])) {
            $imagePath = $data['image_path'];
        }

        $itemData = array_intersect_key($data, array_flip([
            'category_id', 'title_description', 'latitude', 'longitude', 'location_name', 'status',
        ]));

        DB::beginTransaction();
        try {
            $report->fill($itemData)->save();

            // Reassign the reporter (Lost → loser_id, Found → finder_id) if a new user_id was submitted
            if (!empty($data['user_id'])) {
                $reporter = User::findOrFail($data['user_id']);

                if ($report->lostItem) {
                    $loser = Loser::firstOrCreate(
                        ['user_id' => $reporter->id],
                        ['matric_number' => $reporter->matric_number ?? ('STUDENT-' . $reporter->id)]
                    );
                    $report->lostItem->update(['loser_id' => $loser->user_id]);
                } elseif ($report->foundItem) {
                    $finder = Finder::firstOrCreate(['user_id' => $reporter->id]);
                    $report->foundItem->update(['finder_id' => $finder->user_id]);
                }
            }

            if ($imagePath) {
                $report->lostItem?->update(['image_path' => $imagePath]);
                $report->foundItem?->update(['image_path' => $imagePath]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to update report: ' . $e->getMessage()], 500);
        }

        $report->load(['category', 'foundItem', 'lostItem']);
        $report->image_url = $imagePath ? Storage::url($imagePath) : null;

        return response()->json(['data' => $report]);
    }

    public function destroy(Item $report)
    {
        $this->authorize('delete', $report);

        DB::beginTransaction();
        try {
            MatchAlert::where('found_item_id', $report->id)
                ->orWhere('lost_item_id', $report->id)
                ->delete();
            ApiLog::where('item_id', $report->id)->delete();
            $report->foundItem?->delete();
            $report->lostItem?->delete();
            $report->delete();
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to delete report: ' . $e->getMessage()], 500);
        }

        return response()->json([], 204);
    }
}
