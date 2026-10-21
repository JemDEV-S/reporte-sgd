<?php

namespace App\Ingesta;

use App\Models\SincronizacionSgd;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Throwable;

class EjecutarSincronizacion
{
    public function __construct(
        private readonly BloqueoIngesta $bloqueo,
        private readonly IngestaIncrementalSgd $incremental,
        private readonly IngestaSgd $completa,
    ) {}

    public function ejecutar(int $id, ?callable $progreso = null): void
    {
        $this->bloqueo->ejecutar(function () use ($id, $progreso) {
            $ejecucion = SincronizacionSgd::findOrFail($id);
            if ($ejecucion->estado !== 'pendiente') {
                return;
            }
            $ejecucion->update(['estado' => 'ejecutando', 'inicio_en' => now()]);
            try {
                if ($ejecucion->origen === 'manual') {
                    $actor = User::find($ejecucion->user_id);
                    if (! $actor || ! Gate::forUser($actor)->allows('plataforma.acceder') || ! Gate::forUser($actor)->allows('fuentes.gestionar')) {
                        $ejecucion->update([
                            'estado' => 'fallida', 'activa' => null, 'fin_en' => now(),
                            'error_resumen' => 'La cuenta solicitante ya no tiene autorización para sincronizar.',
                        ]);

                        return;
                    }
                }
                $resultado = $ejecucion->tipo === 'completa'
                    ? $this->completa->ejecutar($ejecucion->dependencia, $progreso)
                    : $this->incremental->ejecutar($progreso);
                $metricas = $resultado instanceof ResultadoIncremental
                    ? ['candidatos' => $resultado->candidatos, 'altas_cambios' => $resultado->upserts, 'bajas' => $resultado->bajas, 'destinos' => $resultado->destinos]
                    : ['dependencias' => $resultado->dependencias, 'documentos' => $resultado->documentos, 'destinos' => $resultado->destinos];
                $ejecucion->update([
                    'estado' => 'exitosa', 'activa' => null, 'fin_en' => now(),
                    'publicacion_id' => $resultado->publicacion->id, 'metricas' => $metricas,
                ]);
            } catch (Throwable $error) {
                $ejecucion->update([
                    'estado' => 'fallida', 'activa' => null, 'fin_en' => now(),
                    'error_resumen' => 'No se pudo completar la sincronización. La publicación anterior sigue disponible.',
                ]);
                // No devolver mensajes de drivers ni credenciales a la pantalla o la cola.
                logger()->error('Sincronización SGD fallida.', ['sincronizacion_id' => $id, 'tipo_error' => $error::class]);
            }
        });
    }
}
