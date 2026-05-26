<?php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::prefix('dashboard/data')->group(function () {
        Route::get('/categories', [CategoryController::class, 'index']);
        Route::get('/items', [ItemController::class, 'index']);
        Route::post('/items', [ItemController::class, 'store']);
    });

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
        Route::get('/reports', [\App\Http\Controllers\Admin\ReportsController::class, 'index'])->name('reports');
        Route::get('/users', [\App\Http\Controllers\Admin\UsersController::class, 'index'])->name('users');

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

require __DIR__.'/auth.php';
