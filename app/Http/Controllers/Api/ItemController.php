<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ItemController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Item::class, 'item');
    }

    public function index(Request $request): JsonResponse
    {
        $query = Item::query()->with(['category', 'aiTags', 'user']);

        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        if ($request->filled('q')) {
            $query->where('title_description', 'like', '%'.$request->string('q').'%');
        }

        $perPage = max(1, min(100, (int) $request->query('per_page', 20)));

        $page = $query->orderByDesc('id')->paginate($perPage);
        // Ensure frontend receives a publicly accessible URL when available.
        $page->getCollection()->transform(function ($item) {
            $item->image_url = $this->resolveImageUrl($item->image_path);
            return $item;
        });

        return response()->json($page);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'type' => ['required', 'string', 'in:Lost,Found'],
            'title_description' => ['required', 'string', 'max:255'],
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
            'location_name' => ['required', 'string', 'max:255'],
            'image_path' => [
                Rule::requiredIf(fn () => $request->string('type') === 'Found' && !$request->hasFile('image_file')),
                'nullable',
                'string',
                'max:255',
            ],
            'image_file' => [
                Rule::requiredIf(fn () => $request->string('type') === 'Found' && !$request->filled('image_path')),
                'nullable',
                'file',
                'image',
                'max:5120',
            ],
            'status' => ['nullable', 'string', 'in:Pending,Matched,Claimed,Returned'],
        ]);

        if ($request->hasFile('image_file')) {
            $path = $request->file('image_file')->store('lost_placeholders', 'public');
            $data['image_path'] = $path;
        } elseif (empty($data['image_path'])) {
            $data['image_path'] = url('/images/placeholder-item.svg');
        }

        $data['status'] = $data['status'] ?? 'Pending';

        $item = Item::create($data);

        $item = $item->load(['category', 'aiTags', 'user']);
        $item->image_url = $this->resolveImageUrl($item->image_path);

        return response()->json([
            'data' => $item,
        ], 201);
    }

    public function show(Item $item): JsonResponse
    {
        $item = $item->load(['category', 'aiTags', 'user']);
        $item->image_url = $this->resolveImageUrl($item->image_path);

        return response()->json([
            'data' => $item,
        ]);
    }

    public function update(Request $request, Item $item): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'type' => ['sometimes', 'string', 'in:Lost,Found'],
            'title_description' => ['sometimes', 'string', 'max:255'],
            'latitude' => ['sometimes', 'numeric'],
            'longitude' => ['sometimes', 'numeric'],
            'location_name' => ['sometimes', 'string', 'max:255'],
            'image_path' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'in:Pending,Matched,Claimed,Returned'],
        ]);

        $item->fill($data);
        $item->save();

        $item = $item->load(['category', 'aiTags', 'user']);
        $item->image_url = $this->resolveImageUrl($item->image_path);

        return response()->json([
            'data' => $item,
        ]);
    }

    public function destroy(Item $item): JsonResponse
    {
        $item->delete();

        return response()->json(null, 204);
    }

    private function resolveImageUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        return Storage::disk('public')->exists($path) ? '/storage/' . $path : null;
    }
}
