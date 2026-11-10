<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BabyController;
use App\Http\Controllers\EspController;
use App\Http\Controllers\ReadingController;
use Illuminate\Support\Facades\Route;

// ESP32 device, guarded by the shared API key.
Route::middleware('api.key')->post('/readings', [EspController::class, 'store']);

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);

    Route::apiResource('babies', BabyController::class);
    Route::post('/babies/{baby}/activate', [BabyController::class, 'activate']);
    Route::post('/babies/{baby}/deactivate', [BabyController::class, 'deactivate']);

    Route::get('/babies/{baby}/readings/latest', [ReadingController::class, 'latest']);
    Route::get('/babies/{baby}/readings', [ReadingController::class, 'index']);
});
