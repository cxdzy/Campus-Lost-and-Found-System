<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FoundItem;
use App\Models\Item;
use App\Models\Loser;
use App\Models\LostItem;
use App\Services\MatchingService;
use App\Services\MockCloudVisionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ItemController extends Controller
{
    public function __construct(
        private MockCloudVisionService $visionService,
        private MatchingService        $matchingService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Item::query()
            ->with(['category', 'foundItem.aiTags', 'lostItem'])
            ->when($request->filled('type'), function ($q) use ($request) {
                $type = $request->string('type');
                if ($type === 'Found') {
                    $q->whereHas('foundItem');
                } else {
                    $q->whereHas('lostItem');
                }
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->when($request->filled('loser_id'), fn ($q) => $q->whereHas('lostItem', fn ($sq) => $sq->where('loser_id', $request->integer('loser_id'))))
            ->when($request->filled('q'), fn ($q) => $q->where('title_description', 'like', '%' . $request->string('q') . '%'));

        // Support filtering by the authenticated user's own items
        if ($request->boolean('mine') && Auth::check()) {
            /** @var \App\Models\User $user */
            $user = Auth::user();
            if ($user->isUser() && $user->loser) {
                $query->whereHas('lostItem', fn ($q) => $q->where('loser_id', $user->loser->user_id));
            }
        }

        $perPage = max(1, min(100, (int) $request->query('per_page', 20)));

        $page = $query->orderByDesc('id')->paginate($perPage);
        $page->getCollection()->transform(fn ($item) => $this->appendImageUrl($item));

        return response()->json($page);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category_id'       => ['required', 'integer', 'exists:categories,id'],
            'type'              => ['required', 'string', 'in:Lost,Found'],
            'title_description' => ['required', 'string', 'max:255'],
            'latitude'          => ['required', 'numeric'],
            'longitude'         => ['required', 'numeric'],
            'location_name'     => ['required', 'string', 'max:255'],
            'image_file'        => [
                Rule::requiredIf(fn () => $request->string('type') === 'Found' && !$request->filled('image_path')),
                'nullable', 'file', 'image', 'max:5120',
            ],
            'image_path'        => ['nullable', 'string', 'max:500'],
            'status'            => ['nullable', 'string', 'in:Pending,Matched,Claimed'],
        ]);

        $user = Auth::user();

        DB::beginTransaction();
        try {
            $item = Item::create([
                'category_id'       => $data['category_id'],
                'title_description' => $data['title_description'],
                'latitude'          => $data['latitude'],
                'longitude'         => $data['longitude'],
                'location_name'     => $data['location_name'],
                'status'            => $data['status'] ?? 'Pending',
            ]);

            if ($data['type'] === 'Found') {
                $imagePath = $this->storeImage($request, $data);

                $finderId = ($user && $user->finder) ? $user->finder->user_id : null;

                $foundItem = FoundItem::create([
                    'item_id'    => $item->id,
                    'finder_id'  => $finderId,
                    'image_path' => $imagePath,
                ]);
            } else {
                if (!$user) {
                    DB::rollBack();
                    return response()->json(['message' => 'Authentication required.'], 401);
                }

                // Auto-create a Loser profile if the account pre-dates the TPT schema migration
                if (!$user->loser) {
                    Loser::firstOrCreate(
                        ['user_id' => $user->id],
                        ['matric_number' => $user->matric_number ?? ('STUDENT-' . $user->id)]
                    );
                    $user->load('loser');
                }

                $lostData = [
                    'item_id'  => $item->id,
                    'loser_id' => $user->loser->user_id,
                ];

                // Only include image_path when the column exists in the DB
                if ($request->hasFile('image_file') && \Illuminate\Support\Facades\Schema::hasColumn('lost_items', 'image_path')) {
                    $stored = $request->file('image_file')->store('items', 'public');
                    if ($stored) {
                        $lostData['image_path'] = $stored;
                    }
                }

                $lostItem = LostItem::create($lostData);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to save item: ' . $e->getMessage()], 500);
        }

        // Post-persist processing — failures here must never fail the HTTP response
        try {
            if ($data['type'] === 'Found') {
                $this->visionService->analyse($foundItem);
                $this->matchingService->matchFoundItem($foundItem);
            } else {
                $this->matchingService->matchLostItem($lostItem);
            }
        } catch (\Throwable $e) {
            Log::warning('Post-submit processing error (item saved successfully): ' . $e->getMessage());
        }

        $item->load(['category', 'foundItem.aiTags', 'lostItem']);
        $this->appendImageUrl($item);

        return response()->json(['data' => $item], 201);
    }

    public function show(Item $item): JsonResponse
    {
        $item->load(['category', 'foundItem.aiTags', 'lostItem']);
        $this->appendImageUrl($item);

        return response()->json(['data' => $item]);
    }

    public function update(Request $request, Item $item): JsonResponse
    {
        $data = $request->validate([
            'category_id'       => ['sometimes', 'integer', 'exists:categories,id'],
            'title_description' => ['sometimes', 'string', 'max:255'],
            'latitude'          => ['sometimes', 'numeric'],
            'longitude'         => ['sometimes', 'numeric'],
            'location_name'     => ['sometimes', 'string', 'max:255'],
            'status'            => ['sometimes', 'string', 'in:Pending,Matched,Claimed'],
        ]);

        $item->fill($data)->save();

        // Handle OTP claim resolution: status → Claimed
        if (($data['status'] ?? null) === 'Claimed' && $item->foundItem) {
            $otpInput = $request->string('otp_code');
            $claim    = $item->foundItem->reownershipClaim;
            if ($claim && $claim->otp_code === $otpInput) {
                $claim->update(['claimed_at' => now()]);
            }
        }

        $item->load(['category', 'foundItem.aiTags', 'lostItem']);
        $this->appendImageUrl($item);

        return response()->json(['data' => $item]);
    }

    public function destroy(Item $item): JsonResponse
    {
        $item->delete();
        return response()->json(null, 204);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function storeImage(Request $request, array $data): string
    {
        if ($request->hasFile('image_file')) {
            return $request->file('image_file')->store('items', 'public');
        }

        if (!empty($data['image_path'])) {
            return $data['image_path'];
        }

        return 'placeholder';
    }

    private function appendImageUrl(Item $item): Item
    {
        $imagePath = $item->foundItem?->image_path;
        $item->image_url = $imagePath ? $this->resolveImageUrl($imagePath) : null;
        return $item;
    }

    private function resolveImageUrl(?string $path): ?string
    {
        if (!$path) return null;
        if (Str::startsWith($path, ['http://', 'https://'])) return $path;
        return Storage::disk('public')->exists($path) ? '/storage/' . $path : null;
    }
}
