<?php

use App\Livewire\Babies;
use App\Livewire\Dashboard;
use App\Livewire\History;
use App\Livewire\Login;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/login', Login::class)->middleware('guest')->name('login');

Route::post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('login');
})->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/', Dashboard::class)->name('dashboard');
    Route::get('/bayi', Babies::class)->name('babies');
    Route::get('/riwayat', History::class)->name('history');
});
