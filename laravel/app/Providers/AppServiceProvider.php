<?php

namespace App\Providers;

use App\Enums\PermisoCodigo;
use App\Models\ConfiguracionSincronizacion;
use App\Models\User;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
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
        Queue::looping(function (Looping $evento) {
            if ($evento->connectionName === 'ingesta' && in_array('sgd', explode(',', $evento->queue), true)) {
                ConfiguracionSincronizacion::whereKey(1)
                    ->where(fn ($query) => $query->whereNull('worker_visto_en')->orWhere('worker_visto_en', '<', now()->subSeconds(30)))
                    ->update(['worker_visto_en' => now()]);
            }
        });
        foreach (PermisoCodigo::cases() as $permiso) {
            Gate::define($permiso->value, fn (User $user) => $user->tienePermiso($permiso));
        }
    }
}
