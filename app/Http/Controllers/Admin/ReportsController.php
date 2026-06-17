<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreReportRequest;
use App\Http\Requests\Admin\UpdateReportRequest;
use App\Models\Item;
use Illuminate\Http\Request;
use Inertia\Inertia;
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

        if ($request->hasFile('image_file')) {
            $data['image_path'] = $request->file('image_file')->store('lost_placeholders', 'public');
        }

        $report = Item::create($data);
        $report = $report->load('category');
        $report->image_url = $report->image_path ? Storage::url($report->image_path) : null;

        return response()->json(['data' => $report], 201);
    }

    public function update(UpdateReportRequest $request, Item $report)
    {
        $data = $request->validated();

        if ($request->hasFile('image_file')) {
            $data['image_path'] = $request->file('image_file')->store('lost_placeholders', 'public');
        }

        $report->update($data);
        $report = $report->load('category');
        $report->image_url = $report->image_path ? Storage::url($report->image_path) : null;

        return response()->json(['data' => $report]);
    }

    public function destroy(Item $report)
    {
        $this->authorize('delete', $report);

        $report->delete();
        return response()->json([], 204);
    }
}
