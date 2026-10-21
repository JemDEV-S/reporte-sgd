<?php

namespace App\Ingesta;

use App\Models\ConfiguracionSincronizacion;
use App\Models\Publicacion;
use App\Models\SincronizacionSgd;

class EstadoSincronizacion
{
    public function consultar(): array
    {
        $configuracion = ConfiguracionSincronizacion::findOrFail(1);
        $activa = SincronizacionSgd::where('activa', 1)->first();

        return [
            'configuracion' => $configuracion,
            'activa' => $activa,
            'workerReciente' => $configuracion->worker_visto_en?->greaterThan(now()->subMinutes(2)) ?? false,
            'schedulerReciente' => $configuracion->scheduler_visto_en?->greaterThan(now()->subMinutes(3)) ?? false,
            'textoBoton' => match ($activa?->estado) {
                'ejecutando' => 'Sincronizando…',
                'pendiente' => 'Pendiente de procesamiento',
                default => 'Sincronizar ahora',
            },
            'publicacion' => Publicacion::vigente(),
            'ultimaExitosa' => SincronizacionSgd::where('estado', 'exitosa')->latest('fin_en')->first(),
            'ejecuciones' => SincronizacionSgd::with('responsable')->latest('id')->paginate(20),
        ];
    }
}
