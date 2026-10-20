<?php

namespace App\Console\Commands;

use App\Enums\PermisoCodigo;
use App\Enums\RolCodigo;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Concede datos.ver_personales y datos.ver_asuntos a una cuenta concreta sin
 * afectar al resto de analistas (decisión de producto §4/§7.3: no otorgar estos
 * permisos a todos los analistas por defecto).
 *
 * Como los permisos se resuelven por rol (no hay relación usuario↔permiso), el
 * comando asegura un rol dedicado "analista_pleno" = permisos del analista
 * institucional + los dos sensibles, y se lo asigna a la cuenta por DNI,
 * conservando sus roles actuales. Es idempotente: repetirlo no duplica nada.
 */
class ConcederDatosSensibles extends Command
{
    protected $signature = 'acceso:conceder-datos-sensibles {dni=99999999 : DNI de la cuenta a habilitar}';

    protected $description = 'Habilita nombres y asuntos (datos personales/asuntos) a una cuenta concreta vía un rol dedicado.';

    public function handle(): int
    {
        $dni = (string) $this->argument('dni');

        $usuario = User::where('dni', $dni)->first();
        if ($usuario === null) {
            $this->error("No existe una cuenta con DNI {$dni}.");

            return self::FAILURE;
        }

        DB::transaction(function () use ($usuario, $dni) {
            // Permisos del rol: los iniciales del analista institucional + los dos
            // sensibles. Se garantiza que las filas de Permiso existan.
            $codigos = array_map(
                fn (PermisoCodigo $p) => $p->value,
                RolCodigo::AnalistaInstitucional->permisosIniciales(),
            );
            $codigos[] = PermisoCodigo::VerDatosPersonales->value;
            $codigos[] = PermisoCodigo::VerAsuntos->value;
            $codigos = array_values(array_unique($codigos));

            foreach ($codigos as $codigo) {
                Permiso::firstOrCreate(
                    ['codigo' => $codigo],
                    ['descripcion' => PermisoCodigo::from($codigo)->descripcion()],
                );
            }

            $rol = Rol::firstOrCreate(
                ['codigo' => 'analista_pleno'],
                [
                    'nombre' => 'Analista institucional (datos sensibles)',
                    'descripcion' => 'Analista institucional con acceso a nombres y asuntos. Asignación controlada, no por defecto.',
                ],
            );

            // Mantiene exactamente el conjunto de permisos esperado (idempotente).
            $rol->permisos()->sync(Permiso::whereIn('codigo', $codigos)->pluck('id'));

            // Añade el rol sin quitarle los que ya tenga la cuenta.
            $usuario->roles()->syncWithoutDetaching([$rol->id]);

            $this->info("Rol '{$rol->codigo}' asegurado y asignado al DNI {$dni}.");
        });

        $this->info('Listo: la cuenta ya puede ver nombres y asuntos, y el modal "Ver integrantes".');

        return self::SUCCESS;
    }
}
