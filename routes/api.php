<?php

use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\DeviceTelemetryController;
use App\Http\Controllers\Api\LiveTelemetryController;
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
    Route::post('/ack', [DeviceController::class, 'acknowledge'])->name('api.devices.ack');
});

/*
|--------------------------------------------------------------------------
| Dashboard API Routes (session auth)
|--------------------------------------------------------------------------
*/
Route::get('/incubators/{incubator}/live-telemetry', [LiveTelemetryController::class, 'show'])
    ->name('api.incubators.live-telemetry');
