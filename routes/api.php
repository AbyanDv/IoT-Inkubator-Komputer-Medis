<?php

use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\DeviceTelemetryController;
use App\Http\Middleware\DeviceTokenAuth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Device API Routes (X-Device-Token auth)
|--------------------------------------------------------------------------
*/
Route::middleware(DeviceTokenAuth::class)->prefix('devices')->group(function () {
    Route::post('/heartbeat', [DeviceController::class, 'heartbeat'])->name('api.devices.heartbeat');
    Route::post('/telemetry', [DeviceTelemetryController::class, 'store'])->name('api.devices.telemetry');
    Route::post('/events', [DeviceController::class, 'storeEvent'])->name('api.devices.events');
    Route::get('/config', [DeviceController::class, 'config'])->name('api.devices.config');
});
