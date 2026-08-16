<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
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
        Vite::prefetch(concurrency: 3);

        // Un administrateur/super_admin passe toutes les Policies sans exception,
        // quel que soit le shift concerné (accès total conforme au cahier des charges).
        Gate::before(fn ($user, string $ability) => $user->estAdministrateur() ? true : null);
    }
}
