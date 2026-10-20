<?php

namespace App\Reportes\PendientesDependencia;

use App\Models\Publicacion;

class EvolucionPendientes
{
    /**
     * Último corte por día en Lima. El período termina en la publicación visible,
     * no en el reloj del servidor. Nunca mezcla perfiles ni versiones de reglas.
     * El conjunto de oficinas se fija explícitamente al ámbito visible actual.
     *
     * @return array<int, array{fecha: string, etiqueta: string, corte: string, total: int}>
     */
    public function obtener(ContextoReporte $contexto, array $dependencias, int $dias): array
    {
        $actual = $contexto->publicacion;
        $desde = $actual->fecha_publicacion->copy()->timezone('America/Lima')
            ->startOfDay()->subDays($dias - 1);

        $cortes = Publicacion::query()
            ->whereIn('estado', ['vigente', 'historica'])
            ->where('perfil', $actual->perfil)
            ->where('version_reglas', $actual->version_reglas)
            ->where('fecha_publicacion', '>=', $desde)
            ->where(function ($query) use ($actual) {
                $query->where('fecha_publicacion', '<', $actual->fecha_publicacion)
                    ->orWhere(function ($query) use ($actual) {
                        $query->where('fecha_publicacion', $actual->fecha_publicacion)->where('id', '<=', $actual->id);
                    });
            })
            ->orderBy('fecha_publicacion')->orderBy('id')
            ->get(['id', 'fecha_publicacion'])
            ->keyBy(fn (Publicacion $p) => $p->fecha_publicacion->copy()->timezone('America/Lima')->format('Y-m-d'));

        $totales = (new MotorPendientesDependencia($actual, $contexto->filtros, $contexto))
            ->conteosHistoricos($cortes->pluck('id')->all(), $dependencias);

        return $cortes->map(function (Publicacion $p, string $fecha) use ($totales) {
            $local = $p->fecha_publicacion->copy()->timezone('America/Lima');

            return [
                'fecha' => $fecha,
                'etiqueta' => $local->format('d/m'),
                'corte' => $local->format('d/m/Y H:i'),
                'total' => (int) ($totales[$p->id] ?? 0),
            ];
        })->values()->all();
    }
}
