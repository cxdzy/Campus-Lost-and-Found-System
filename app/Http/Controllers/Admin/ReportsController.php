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

        $query = Item::query()->with('category');

        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }

        $reports = $query->latest()->limit(200)->get();

        if ($request->wantsJson()) {
            return response()->json(['data' => $reports]);
        }

        return Inertia::render('Admin/Reports', [
            'reports' => $reports,
        ]);
    }

    public function show(Item $report)
    {
        $this->authorize('view', $report);

        return response()->json(['data' => $report->load('category')]);
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
