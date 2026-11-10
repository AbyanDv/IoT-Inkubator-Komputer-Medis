<?php

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
    Route::post('/telemetry', [DeviceTelemetryController::class, 'store'])
        ->name('api.devices.telemetry');
});

/*
|--------------------------------------------------------------------------
| Dashboard API Routes (session auth)
|--------------------------------------------------------------------------
*/
Route::get('/incubators/{incubator}/live-telemetry', [LiveTelemetryController::class, 'show'])
    ->name('api.incubators.live-telemetry');
