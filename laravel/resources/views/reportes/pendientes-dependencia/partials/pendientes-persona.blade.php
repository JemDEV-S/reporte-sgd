@php
    use App\Reportes\PendientesDependencia\FilaDetalle;

    /**
     * Fragmento con los pendientes de una persona dentro de una dependencia (§5).
     * Reutiliza el parcial de tabla del detalle con el mismo contexto, filtros y
     * orden. Recibe:
     *   $detalle          DetallePendientes (restringido a una persona o al nulo)
     *   $empleado         string (código del empleado)
     *   $sinDestinatario  bool (grupo "sin destinatario identificado")
     *
     * Marcado para el JS del modal:
     *   [data-persona-fragmento]  raíz del fragmento
     *   [data-volver-padron]      enlace de vuelta al padrón
     *
     * El nombre se toma de la primera fila (ya proyectada con permiso); este
     * endpoint exige datos.ver_personales, de modo que el nombre es visible.
     */
    $primera = $detalle->destinos->first();
    $nombre = $sinDestinatario
        ? 'Sin destinatario identificado'
        : ($primera instanceof FilaDetalle && $primera->destinatarioNombre !== null
            ? $primera->destinatarioNombre
            : 'Empleado '.$empleado);

    $filtrosQuery = array_filter([
        'prioridad' => $detalle->filtros->prioridad,
        'estado' => $detalle->filtros->estadoRecepcion,
        'bandeja' => $detalle->filtros->bandeja,
        'publicacion' => request()->query('publicacion'),
    ], fn ($v) => $v !== null);
    $volver = route('reportes.pendientes-dependencia.integrantes', ['dependencia' => $detalle->codigo, ...$filtrosQuery]);
@endphp

<div data-persona-fragmento>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="{{ $volver }}" data-volver-padron class="text-sm font-semibold text-emerald hover:underline">← Volver a integrantes</a>
            <p class="mt-1 text-sm font-semibold text-ink">{{ $nombre }}</p>
            <p class="text-xs text-muted">
                {{ $detalle->nombre() }} · {{ $detalle->codigo }} ·
                Corte {{ $detalle->fechaCorte->translatedFormat('d \d\e F \d\e Y') }} ·
                {{ number_format($detalle->totalDestinos()) }} pendiente(s)
            </p>
        </div>
    </div>

    @include('reportes.pendientes-dependencia.partials.tabla-destinos', [
        'filas' => $detalle->destinos,
        'corte' => $detalle->fechaCorte,
    ])

    @if ($detalle->destinos->hasPages())
        <div class="mt-4">{{ $detalle->destinos->links() }}</div>
    @endif
</div>
