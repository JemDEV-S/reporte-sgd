<?php

namespace App\Administracion;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class GestionarUsuario
{
    public function __construct(
        private readonly ProtegerAccesoAdministrativo $proteccion,
        private readonly RegistrarAuditoria $auditoria,
    ) {}

    public function guardar(User $actor, ?User $usuario, array $datos): User
    {
        return DB::transaction(function () use ($actor, $usuario, $datos) {
            $this->proteccion->bloquear($actor, 'usuarios.gestionar');
            $nuevo = $usuario === null;
            $usuario = $nuevo ? new User : User::query()->lockForUpdate()->findOrFail($usuario->id);
            $rolesAnteriores = $nuevo ? [] : $usuario->roles()->pluck('roles.id')->map(fn ($id) => (int) $id)->all();
            $roles = array_map('intval', $datos['roles'] ?? []);
            sort($rolesAnteriores);
            sort($roles);

            // Gestionar perfiles no permite tomar el control de cuentas privilegiadas.
            if (! $nuevo && ($usuario->tienePermiso('usuarios.gestionar') || $usuario->tienePermiso('roles.gestionar'))) {
                Gate::forUser($actor)->authorize('roles.gestionar');
            }
            if ($roles !== $rolesAnteriores) {
                Gate::forUser($actor)->authorize('roles.gestionar');
            }

            $usuario->fill(Arr::only($datos, ['dni', 'nombre', 'apellidos', 'email']));
            $usuario->activo = (bool) $datos['activo'];
            $cambiaPassword = filled($datos['password'] ?? null);
            if ($cambiaPassword) {
                $usuario->password = $datos['password'];
                $usuario->remember_token = null;
            }
            $campos = array_values(array_diff(array_keys($usuario->getDirty()), ['password', 'remember_token']));
            $usuario->save();
            $usuario->roles()->sync($roles);
            $this->proteccion->verificar();
            $this->auditoria->registrar($actor, $nuevo ? 'usuario.creado' : 'usuario.actualizado', 'usuario', $usuario->id, [
                'campos' => $campos,
                'roles_antes' => $rolesAnteriores,
                'roles_despues' => $roles,
                'password_actualizado' => $cambiaPassword,
            ]);

            return $usuario;
        });
    }
}
