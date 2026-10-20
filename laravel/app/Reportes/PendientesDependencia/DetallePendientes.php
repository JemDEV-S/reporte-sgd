<?php

namespace App\Reportes\PendientesDependencia;

use App\Models\Dependencia;
use App\Models\DestinoCaptura;
use App\Models\Publicacion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/**
 * Detalle de los destinos pendientes de una dependencia: la lista paginada de
 * asignaciones con sus claves y antigüedad, y la distribución por tramo de
 * antigüedad para el gráfico.
 */
class DetallePendientes
{
    /**
     * @param  LengthAwarePaginator<int, array{modelo: DestinoCaptura, tramo: TramoAntiguedad, dias: ?int}>  $destinos
     * @param  array<string, int>  $distribucionAntiguedad
     */
    public function __construct(
        public readonly Publicacion $publicacion,
        public readonly FiltrosPendientes $filtros,
        public readonly ?Dependencia $dependencia,
        public readonly string $codigo,
        public readonly LengthAwarePaginator $destinos,
        public readonly array $distribucionAntiguedad,
        public readonly Carbon $fechaCorte,
    ) {}

    public function nombre(): string
    {
        return $this->dependencia?->nombre ?? "Dependencia {$this->codigo}";
    }

    public function totalDestinos(): int
    {
        return $this->destinos->total();
    }

    /** Mayor conteo entre tramos, para escalar las barras del gráfico. */
    public function maximoTramo(): int
    {
        return max(1, ...array_values($this->distribucionAntiguedad));
    }
}
