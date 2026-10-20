<?php

namespace Database\Seeders;

use App\Enums\PermisoCodigo;
use App\Enums\RolCodigo;
use App\Models\Permiso;
use App\Models\Rol;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolesPermisosSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            foreach (PermisoCodigo::cases() as $permiso) {
                Permiso::updateOrCreate(
                    ['codigo' => $permiso->value],
                    ['descripcion' => $permiso->descripcion()],
                );
            }

            foreach (RolCodigo::cases() as $codigo) {
                $rol = Rol::firstOrCreate(
                    ['codigo' => $codigo->value],
                    ['nombre' => $codigo->nombre(), 'descripcion' => $codigo->descripcion()],
                );

                // Las ejecuciones posteriores conservan los permisos administrados.
                if ($rol->wasRecentlyCreated) {
                    $codigos = array_map(fn (PermisoCodigo $permiso) => $permiso->value, $codigo->permisosIniciales());
                    $rol->permisos()->sync(Permiso::whereIn('codigo', $codigos)->pluck('id'));
                }
            }
        });
    }
}
