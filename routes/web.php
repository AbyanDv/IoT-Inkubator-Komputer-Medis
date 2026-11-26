<?php

use App\Http\Controllers\Api\LiveTelemetryController;
use App\Http\Controllers\BabyController;
use App\Http\Controllers\ConfigController;
use App\Http\Controllers\FirmwareGuideController;
use App\Http\Controllers\IncubatorController;
use App\Http\Controllers\RelayController;
use App\Http\Controllers\SessionController;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Redirect::route('incubators.index');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->name('login.store');
});

Route::post('/logout', [SessionController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/firmware-guide', [FirmwareGuideController::class, 'show'])->name('firmware.guide');
    Route::get('/babies', [BabyController::class, 'index'])->name('babies.index');
    Route::post('/babies', [BabyController::class, 'store'])->name('babies.store');
    Route::get('/babies/{baby}', [BabyController::class, 'show'])->name('babies.show');
    Route::put('/babies/{baby}', [BabyController::class, 'update'])->name('babies.update');
    Route::delete('/babies/{baby}', [BabyController::class, 'destroy'])->name('babies.destroy');
    Route::post('/babies/{baby}/deactivate', [BabyController::class, 'deactivate'])->name('babies.deactivate');
    Route::get('/incubators', [IncubatorController::class, 'index'])->name('incubators.index');
    Route::get('/api/incubators/{incubator}/live-telemetry', [LiveTelemetryController::class, 'show'])
        ->name('api.incubators.live-telemetry');
    Route::get('/incubators/{incubator}', [IncubatorController::class, 'show'])->name('incubators.show');
    Route::post('/incubators/{incubator}/pair', [IncubatorController::class, 'pair'])
        ->middleware('can:manage-pairing')
        ->name('incubators.pair');
    Route::post('/incubators/{incubator}/unpair', [IncubatorController::class, 'unpair'])
        ->middleware('can:manage-pairing')
        ->name('incubators.unpair');
    Route::get('/incubators/{incubator}/config', [ConfigController::class, 'edit'])
        ->middleware('can:manage-config')
        ->name('incubators.config.edit');
    Route::post('/incubators/{incubator}/config', [ConfigController::class, 'update'])
        ->middleware('can:manage-config')
        ->name('incubators.config.update');
    Route::post('/incubators/{incubator}/relays', [RelayController::class, 'update'])
        ->middleware('can:manage-config')
        ->name('incubators.relays.update');
    Route::post('/incubators/{incubator}/relays/auto', [RelayController::class, 'auto'])
        ->middleware('can:manage-config')
        ->name('incubators.relays.auto');
});
