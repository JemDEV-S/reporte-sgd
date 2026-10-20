<?php

namespace App\Reportes\PendientesDependencia;

use App\Models\DestinoCaptura;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filtros explícitos del reporte. Se aplican por igual al resumen y al
 * detalle para que ambos cuenten exactamente el mismo universo.
 *
 * El alcance por dependencia autorizada (según el usuario) se expresa con
 * $dependenciasAutorizadas: null significa "todas" (perfil institucional).
 */
class FiltrosPendientes
{
    public function __construct(
        public readonly ?string $prioridad = null,
        public readonly ?string $estadoRecepcion = null,
        public readonly ?string $bandeja = null, // 'entrada' | 'delegados'
        /** @var array<int, string>|null códigos de dependencia autorizados */
        public readonly ?array $dependenciasAutorizadas = null,
    ) {}

    /**
     * @param  Builder<DestinoCaptura>  $query
     * @return Builder<DestinoCaptura>
     */
    public function aplicar(Builder $query): Builder
    {
        if ($this->prioridad !== null) {
            $query->where('destinos_captura.prioridad', $this->prioridad);
        }

        if ($this->estadoRecepcion !== null) {
            $query->where('destinos_captura.estado_recepcion', $this->estadoRecepcion);
        }

        if ($this->bandeja === 'entrada') {
            $query->whereColumn('destinos_captura.destinatario_empleado', 'destinos_captura.encargado_empleado');
        } elseif ($this->bandeja === 'delegados') {
            $query->whereColumn('destinos_captura.destinatario_empleado', '!=', 'destinos_captura.encargado_empleado');
        }

        if ($this->dependenciasAutorizadas !== null) {
            $query->whereIn('destinos_captura.dependencia_codigo', $this->dependenciasAutorizadas);
        }

        return $query;
    }

    /** @return array<string, string|null> filtros activos para mostrarlos en la salida */
    public function descripcion(): array
    {
        return array_filter([
            'Prioridad' => $this->prioridad,
            'Estado de recepción' => $this->estadoRecepcion,
            'Bandeja' => $this->bandeja,
        ], fn ($v) => $v !== null);
    }
}
