<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\LinkedInController;
use App\Http\Controllers\AnalyticsController;

// Test route
Route::get('/test', function () {
    return response()->json([
        'status'  => 'success',
        'message' => 'AI Blog Laravel API is running!'
    ]);
});

// Blog routes
Route::post('/generate',     [BlogController::class, 'generate']);
Route::get('/blogs',         [BlogController::class, 'index']);
Route::get('/blogs/{id}',    [BlogController::class, 'show']);
Route::delete('/blogs/{id}', [BlogController::class, 'destroy']);

// Image routes
Route::post('/upload-image',   [ImageController::class, 'upload']);
Route::delete('/delete-image', [ImageController::class, 'delete']);

// LinkedIn routes
Route::post('/auth/linkedin/exchange', [LinkedInController::class, 'exchange']);
Route::post('/publish/linkedin',       [LinkedInController::class, 'publish']);

// Analytics route
Route::get('/analytics', [AnalyticsController::class, 'stats']);