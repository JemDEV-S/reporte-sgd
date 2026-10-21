<?php

namespace App\Ingesta;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class BloqueoIngesta
{
    /** PostgreSQL libera el bloqueo de sesión incluso si el proceso termina abruptamente. */
    public function ejecutar(Closure $operacion): mixed
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $lock = Cache::lock('sirmd:ingesta', 7200);
            if (! $lock->get()) {
                throw new IngestaOcupada('Ya hay una sincronización en curso.');
            }
            try {
                return $operacion();
            } finally {
                $lock->release();
            }
        }

        // Conservar el PDO original evita liberar un bloqueo en una sesión reconectada.
        $pdo = DB::connection()->getPdo();
        $clave = "hashtext(current_database()), hashtext(current_schema() || ':sirmd-ingesta')";
        $adquirido = $pdo->query("SELECT pg_try_advisory_lock({$clave})")->fetchColumn();
        if (! in_array($adquirido, [true, 1, '1', 't'], true)) {
            throw new IngestaOcupada('Ya hay una sincronización en curso.');
        }
        try {
            return $operacion();
        } finally {
            try {
                $pdo->query("SELECT pg_advisory_unlock({$clave})");
            } catch (Throwable $error) {
                logger()->warning('No se pudo liberar la sesión de bloqueo de ingesta; revisar la conexión PostgreSQL.');
            }
        }
    }
}
