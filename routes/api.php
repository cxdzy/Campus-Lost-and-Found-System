<?php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\BotSubmissionController;
use App\Http\Controllers\Api\ItemController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
	Route::get('/categories', [CategoryController::class, 'index']);

	Route::get('/items', [ItemController::class, 'index']);
	Route::post('/items', [ItemController::class, 'store']);
	Route::get('/items/{item}', [ItemController::class, 'show']);
	Route::patch('/items/{item}', [ItemController::class, 'update']);
	Route::delete('/items/{item}', [ItemController::class, 'destroy']);
});

Route::post('/bot/submit', [BotSubmissionController::class, 'store']);
