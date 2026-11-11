<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('manage-pairing', fn ($user): bool => $user->hasRole(['admin', 'super_admin']));
        Gate::define('manage-config', fn ($user): bool => $user->hasRole(['admin', 'super_admin']));
    }
}
