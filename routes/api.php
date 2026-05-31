<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\AIController;
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