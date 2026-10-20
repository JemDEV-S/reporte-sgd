<?php

namespace App\Providers;

use App\Enums\PermisoCodigo;
use App\Models\User;
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
        foreach (PermisoCodigo::cases() as $permiso) {
            Gate::define($permiso->value, fn (User $user) => $user->tienePermiso($permiso));
        }
    }
}
