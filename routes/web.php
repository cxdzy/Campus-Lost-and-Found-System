<?php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\ProfileController;
use App\Models\Category;
use App\Models\Item;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Resolve a public-facing image URL from a found_items image_path.
 * Always returns the /storage/ prefixed path — never null — so the
 * Docker fallback route (/storage/items/{filename}) can serve the file
 * even when the public symlink is absent.
 */
function resolveItemPayload(Item $item): array
{
    // Found items carry their image; lost items may carry an optional reference image
    $raw = $item->foundItem?->image_path ?? $item->lostItem?->image_path;

    if (!$raw) {
        $imageUrl = null;
    } elseif (Str::startsWith($raw, ['http://', 'https://'])) {
        $imageUrl = $raw;
    } else {
        $imageUrl = '/storage/' . $raw;
    }

    return [
        'title_description' => $item->title_description,
        'category'          => $item->category?->category_name,
        'location_name'     => $item->location_name,
        'image_url'         => $imageUrl,
        'image_path'        => $imageUrl,
    ];
}

Route::get('/', function () {
    $items = [];

    if (Schema::hasTable('items')) {
        $items = Item::query()
            ->with(['category', 'foundItem'])
            ->has('foundItem')
            ->latest()
            ->take(12)
            ->get()
            ->map(fn (Item $item) => [
                'id'          => $item->id,
                'image_url'   => resolveItemPayload($item)['image_url'],
                'category'    => $item->category?->category_name,
                'description' => $item->title_description,
            ])
            ->all();
    }

    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
        'items' => $items,
    ]);
});

Route::get('/dashboard', function (Request $request) {
    $items = [];
    $myReports = [];
    $user = $request->user();

    if (Schema::hasTable('items')) {
        $items = Item::query()
            ->with(['category', 'foundItem'])
            ->has('foundItem')
            ->latest()
            ->take(20)
            ->get()
            ->map(fn (Item $item) => array_merge(
                resolveItemPayload($item),
                [
                    'id'         => $item->id,
                    'created_at' => $item->created_at,
                    'latitude'   => $item->latitude,
                    'longitude'  => $item->longitude,
                ]
            ))
            ->all();

        if ($user && $user->loser) {
            $myReports = Item::query()
                ->with(['category', 'lostItem'])
                ->whereHas('lostItem', fn ($q) => $q->where('loser_id', $user->loser->user_id))
                ->latest()
                ->take(20)
                ->get()
                ->map(fn (Item $item) => array_merge(
                    resolveItemPayload($item),
                    ['id' => $item->id, 'created_at' => $item->created_at, 'status' => $item->status, 'latitude' => $item->latitude, 'longitude' => $item->longitude]
                ))
                ->all();
        }
    }

    // Auto-seed categories if the table is empty or only has the bot-created "Others" row
    if (Schema::hasTable('categories') && Category::count() < 2) {
        Artisan::call('db:seed', ['--class' => 'CategorySeeder', '--force' => true]);
    }

    $categories = Schema::hasTable('categories')
        ? Category::orderBy('category_name')->get(['id', 'category_name', 'icon_identifier'])->all()
        : [];

    $alertCount = 0;
    if ($user && $user->loser && Schema::hasTable('match_alerts')) {
        $loserItemIds = \App\Models\LostItem::where('loser_id', $user->loser->user_id)
            ->pluck('item_id');
        $alertCount = \App\Models\MatchAlert::whereIn('lost_item_id', $loserItemIds)->where('is_notified', false)->count();
    }

    return Inertia::render('Dashboard', [
        'items'      => $items,
        'myReports'  => $myReports,
        'categories' => $categories,
        'alertCount' => $alertCount,
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::prefix('dashboard/data')->group(function () {
        Route::get('/categories', [CategoryController::class, 'index']);
        Route::get('/items', [ItemController::class, 'index']);
        Route::post('/items', [ItemController::class, 'store']);
        Route::delete('/items/{item}', [ItemController::class, 'destroy']);
    });

    Route::post('/dashboard/generate-otp/{item}', [\App\Http\Controllers\StudentClaimController::class, 'requestOtp'])
        ->name('dashboard.generate-otp');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Admin routes (separate area for Admin / Security staff)
Route::prefix('admin')->name('admin.')->group(function () {
    // Admin login (guest)
    Route::get('/login', [\App\Http\Controllers\Admin\Auth\AuthenticatedSessionController::class, 'create'])->name('login')->middleware('guest');
    Route::post('/login', [\App\Http\Controllers\Admin\Auth\AuthenticatedSessionController::class, 'store'])->middleware('guest');

    Route::get('/', function () {
        $user = Auth::user();

        if ($user && in_array($user->role, ['Admin', 'Security'], true)) {
            return redirect()->route('admin.dashboard');
        }

        return redirect()->route('admin.login');
    })->name('home');

    // Protected admin area
    Route::middleware(['auth', \App\Http\Middleware\EnsureUserRole::class.':Admin,Security'])->group(function () {
        Route::get('/dashboard', [\App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('dashboard');
        Route::delete('/items/{item}', [\App\Http\Controllers\Admin\DashboardController::class, 'destroy'])->name('items.destroy');
        Route::get('/reports', [\App\Http\Controllers\Admin\ReportsController::class, 'index'])->name('reports');
        Route::get('/users', [\App\Http\Controllers\Admin\UsersController::class, 'index'])->name('users');
        Route::get('/match-alerts', [\App\Http\Controllers\Admin\MatchAlertsController::class, 'index'])->name('match-alerts');
        Route::post('/match-alerts/{matchAlert}/verify', [\App\Http\Controllers\Admin\MatchAlertsController::class, 'verify'])->name('match-alerts.verify');
        Route::post('/match-alerts/{matchAlert}/confirm-otp', [\App\Http\Controllers\Admin\MatchAlertsController::class, 'confirmOtp'])->name('match-alerts.confirm-otp');

        // Admin API endpoints for managing users and reports
        Route::prefix('api')->name('api.')->group(function () {
            // Users
            Route::get('/users', [\App\Http\Controllers\Admin\UsersController::class, 'index']);
            Route::post('/users', [\App\Http\Controllers\Admin\UsersController::class, 'store']);
            Route::get('/users/{user}', [\App\Http\Controllers\Admin\UsersController::class, 'show']);
            Route::patch('/users/{user}', [\App\Http\Controllers\Admin\UsersController::class, 'update']);
            Route::delete('/users/{user}', [\App\Http\Controllers\Admin\UsersController::class, 'destroy']);

            // Reports
            Route::get('/reports', [\App\Http\Controllers\Admin\ReportsController::class, 'index']);
            Route::post('/reports', [\App\Http\Controllers\Admin\ReportsController::class, 'store']);
            Route::get('/reports/{report}', [\App\Http\Controllers\Admin\ReportsController::class, 'show']);
            Route::patch('/reports/{report}', [\App\Http\Controllers\Admin\ReportsController::class, 'update']);
            Route::delete('/reports/{report}', [\App\Http\Controllers\Admin\ReportsController::class, 'destroy']);
        });
        Route::post('/logout', [\App\Http\Controllers\Admin\Auth\AuthenticatedSessionController::class, 'destroy'])->name('logout');
    });
});

// Temporary one-shot route to create the storage symlink inside the deployed container.
// Visit /symlink-fix once after each fresh deployment, then this route can be removed.
Route::get('/symlink-fix', function () {
    \Illuminate\Support\Facades\Artisan::call('storage:link');
    return 'Storage linked successfully!';
});

// Temporary one-shot route to seed categories into the live database.
// Visit /seed-categories once after deployment if the dropdown shows only "Others".
Route::get('/seed-categories', function () {
    \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => 'CategorySeeder', '--force' => true]);
    $categories = \App\Models\Category::orderBy('category_name')->pluck('category_name');
    return 'Categories seeded: ' . $categories->join(', ');
});

// Temporary: run any pending migrations instantly without a full redeploy.
Route::get('/run-migrations', function () {
    Artisan::call('migrate', ['--force' => true]);
    $output = Artisan::output();
    return '<pre>' . $output . '</pre>Done.';
});

// ── Docker/Dokploy Symlink Bypass ───────────────────────────────────────────
// If the web server fails to resolve the public/storage symlink, Laravel
// intercepts the request and serves the file directly from internal storage.
Route::get('/storage/items/{filename}', function (string $filename) {
    $path = storage_path('app/public/items/' . $filename);

    if (!file_exists($path)) {
        abort(404, 'Image not found on disk.');
    }

    $mimeType = \Illuminate\Support\Facades\File::mimeType($path);

    return response()->file($path, [
        'Content-Type'  => $mimeType,
        'Cache-Control' => 'public, max-age=86400',
    ]);
})->where('filename', '.*');

require __DIR__.'/auth.php';
