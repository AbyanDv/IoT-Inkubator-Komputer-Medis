<?php

use App\Http\Controllers\ConfigController;
use App\Http\Controllers\IncubatorController;
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
    Route::get('/incubators', [IncubatorController::class, 'index'])->name('incubators.index');
    Route::get('/incubators/{incubator}', [IncubatorController::class, 'show'])->name('incubators.show');
    Route::post('/incubators/{incubator}/pair', [IncubatorController::class, 'pair'])
        ->middleware('can:manage-pairing')
        ->name('incubators.pair');
    Route::post('/incubators/{incubator}/unpair', [IncubatorController::class, 'unpair'])
        ->middleware('can:manage-pairing')
        ->name('incubators.unpair');
    Route::get('/incubators/{incubator}/config', [ConfigController::class, 'edit'])->name('incubators.config.edit');
    Route::post('/incubators/{incubator}/config', [ConfigController::class, 'update'])->name('incubators.config.update');
});
