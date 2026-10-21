<?php

namespace App\Administracion;

use App\Models\Rol;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class GestionarRol
{
    public function __construct(
        private readonly ProtegerAccesoAdministrativo $proteccion,
        private readonly RegistrarAuditoria $auditoria,
    ) {}

    public function guardar(User $actor, ?Rol $rol, array $datos): Rol
    {
        return DB::transaction(function () use ($actor, $rol, $datos) {
            $this->proteccion->bloquear($actor, 'roles.gestionar');
            $nuevo = $rol === null;
            $rol = $nuevo ? new Rol(['codigo' => $datos['codigo']]) : Rol::query()->findOrFail($rol->id);
            $antes = $nuevo ? [] : $rol->permisos()->pluck('permisos.id')->all();
            $rol->fill(['nombre' => $datos['nombre'], 'descripcion' => $datos['descripcion'] ?? '']);
            $rol->save();
            $rol->permisos()->sync($datos['permisos'] ?? []);
            $this->proteccion->verificar();
            $this->auditoria->registrar($actor, $nuevo ? 'rol.creado' : 'rol.actualizado', 'rol', $rol->id, [
                'permisos_antes' => $antes,
                'permisos_despues' => array_map('intval', $datos['permisos'] ?? []),
            ]);

            return $rol;
        });
    }
}
