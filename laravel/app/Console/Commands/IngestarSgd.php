<?php

namespace App\Console\Commands;

use App\Ingesta\EjecutarSincronizacion;
use App\Ingesta\ProgramarSincronizacion;
use Illuminate\Console\Command;
use Throwable;

class IngestarSgd extends Command
{
    protected $signature = 'sgd:ingestar
        {--dependencia= : Limitar la ingesta completa a una dependencia (piloto)}
        {--incremental : Aplicar cambios desde la última marca de agua}
        {--sin-confirmar : Omitir la confirmación antes de una ingesta completa}';

    protected $description = 'Sincroniza el SGD con bloqueo compartido e historial de ejecución';

    public function handle(ProgramarSincronizacion $programador, EjecutarSincronizacion $ejecutor): int
    {
        $dependencia = $this->option('dependencia');
        $dependencia = is_string($dependencia) && trim($dependencia) !== '' ? trim($dependencia) : null;
        if ($dependencia !== null && (! preg_match('/^[0-9A-Za-z]{1,5}$/', $dependencia) || $this->option('incremental'))) {
            $this->error('La dependencia debe ser un código válido y solo se admite en ingestas completas.');

            return self::FAILURE;
        }
        $incremental = (bool) $this->option('incremental');
        if (! $incremental && $dependencia === null && ! $this->option('sin-confirmar')
            && ! $this->confirm('Vas a ejecutar una ingesta COMPLETA del SGD. ¿Continuar?')) {
            $this->line('Ingesta cancelada.');

            return self::SUCCESS;
        }
        try {
            $programador->recuperarInterrumpidas();
            $ejecucion = $programador->solicitar(encolar: false, tipo: $incremental ? 'incremental' : 'completa', dependencia: $dependencia);
            $ejecutor->ejecutar($ejecucion->id, fn (int $n) => $this->line("  … {$n} registros procesados"));
            $ejecucion->refresh();
            if ($ejecucion->estado !== 'exitosa') {
                $this->error($ejecucion->error_resumen ?? 'Ya existe otra sincronización en curso.');

                return self::FAILURE;
            }
            $this->info("Sincronización #{$ejecucion->id} completada. Publicación #{$ejecucion->publicacion_id} vigente.");
            $this->table(['Métrica', 'Valor'], collect($ejecucion->metricas)->map(fn ($valor, $clave) => [$clave, $valor])->values()->all());

            return self::SUCCESS;
        } catch (Throwable $error) {
            $this->error('No se pudo iniciar la sincronización. Revisa el estado en Administración → Sincronización SGD.');

            return self::FAILURE;
        }
    }
}
