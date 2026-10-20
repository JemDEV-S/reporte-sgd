<?php

namespace App\Reportes\PendientesDependencia;

use App\Models\Dependencia;
use App\Models\Publicacion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Padrón de integrantes activos de una dependencia y la conciliación de sus
 * pendientes (§5.3): los integrantes del padrón (paginados, con ceros) y el
 * grupo separado de "otras asignaciones" (destinatarios fuera del padrón
 * activo, incluido el destinatario nulo).
 *
 * Identidad obligatoria para la misma publicación y filtros:
 *   pendientes de integrantes + pendientes fuera del padrón = total del detalle.
 */
class PadronIntegrantes
{
    /**
     * @param  LengthAwarePaginator<int, IntegrantePadron>  $integrantes
     * @param  array<int, IntegrantePadron>  $otrasAsignaciones
     */
    public function __construct(
        public readonly Publicacion $publicacion,
        public readonly FiltrosPendientes $filtros,
        public readonly ?Dependencia $dependencia,
        public readonly string $codigo,
        public readonly LengthAwarePaginator $integrantes,
        public readonly array $otrasAsignaciones,
        /** Total de pendientes de TODO el padrón activo (todas las páginas). */
        public readonly int $totalIntegrantes,
        /** Total de pendientes del grupo "otras asignaciones". */
        public readonly int $totalOtras,
        /** Total del detalle de la dependencia (para verificar la identidad). */
        public readonly int $totalDetalle,
    ) {}

    public function nombre(): string
    {
        return $this->dependencia?->nombre ?? "Dependencia {$this->codigo}";
    }

    /** Cantidad de integrantes activos del padrón (todas las páginas). */
    public function totalPersonas(): int
    {
        return $this->integrantes->total();
    }

    public function tieneOtrasAsignaciones(): bool
    {
        return $this->otrasAsignaciones !== [];
    }

    /**
     * ¿Se cumple la identidad de conciliación (§5.3)? Debe ser siempre verdadera;
     * las pruebas la exigen y la vista puede señalar la incidencia si falla.
     */
    public function concilia(): bool
    {
        return $this->totalIntegrantes + $this->totalOtras === $this->totalDetalle;
    }
}
