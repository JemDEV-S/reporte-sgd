<?php

namespace App\Administracion;

use App\Models\Rol;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ProtegerAccesoAdministrativo
{
    /** Todas las escrituras administrativas adquieren los mismos bloqueos en orden. */
    public function bloquear(User $actor, string $permiso): void
    {
        Rol::query()->orderBy('id')->lockForUpdate()->get();
        Gate::forUser($actor)->authorize('plataforma.acceder');
        Gate::forUser($actor)->authorize($permiso);
    }

    public function verificar(): void
    {
        $existe = User::query()->where('activo', true)
            ->whereHas('roles.permisos', fn ($query) => $query->where('codigo', 'plataforma.acceder'))
            ->whereHas('roles.permisos', fn ($query) => $query->where('codigo', 'usuarios.gestionar'))
            ->whereHas('roles.permisos', fn ($query) => $query->where('codigo', 'roles.gestionar'))
            ->exists();

        if (! $existe) {
            throw ValidationException::withMessages([
                'administracion' => 'Debe quedar al menos una cuenta activa con acceso a la plataforma y gestión de usuarios y roles.',
            ]);
        }
    }
}
