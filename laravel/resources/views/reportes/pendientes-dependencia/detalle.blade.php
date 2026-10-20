@php
    use App\Reportes\PendientesDependencia\TramoAntiguedad;
    use Illuminate\Support\Carbon;

    $filtrosQuery = array_filter([
        'prioridad' => $filtros->prioridad,
        'estado' => $filtros->estadoRecepcion,
        'bandeja' => $filtros->bandeja,
        'publicacion' => $detalle->publicacion->id,
        'periodo' => request()->query('periodo'),
    ], fn ($valor) => $valor !== null && $valor !== '');
    $volver = route('reportes.pendientes-dependencia', ['dependencia' => $detalle->codigo, ...$filtrosQuery]);
    $exportarCsv = route('reportes.pendientes-dependencia.exportar', ['dependencia' => $detalle->codigo, ...$filtrosQuery]);

    $maxTramo = $detalle->maximoTramo();
    // Intensidad creciente con la antigüedad; cada barra lleva etiqueta y conteo.
    $colorTramo = [
        '0-3' => 'bg-emerald/40',
        '4-7' => 'bg-emerald/60',
        '8-15' => 'bg-amber-400',
        '16-30' => 'bg-orange-500',
        '30+' => 'bg-red-600',
        'incidencia' => 'bg-slate-400',
    ];
@endphp

<x-layouts.platform :areas="$areas" :area="$area">
    <x-page-header icon="reportes" eyebrow="CATÁLOGO DE REPORTES · DETALLE" :title="$detalle->nombre()"
        :description="'Destinos pendientes de la dependencia ' . $detalle->codigo . ', con sus claves, estado, prioridad y antigüedad.'" />

    {{-- Migas de pan --}}
    <nav aria-label="Ruta" class="mb-3 flex flex-wrap items-center gap-2 text-sm">
        <a href="{{ route('reportes.pendientes-dependencia', $filtrosQuery) }}" class="text-emerald hover:underline">Institución</a>
        <span class="text-slate-300" aria-hidden="true">/</span>
        <a href="{{ $volver }}" class="text-emerald hover:underline">Resumen de la dependencia</a>
        <span class="text-slate-300" aria-hidden="true">/</span>
        <span class="font-semibold text-ink">Detalle</span>
    </nav>

    {{-- Cabecera compacta: total y filtros activos (sin repetir los KPIs del resumen) --}}
    <div class="mb-4 flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-line bg-white px-4 py-3">
        <div>
            <p class="text-xs text-muted">{{ $detalle->codigo }} · Destinos pendientes</p>
            <p class="text-3xl font-light tabular-nums text-ink">{{ number_format($detalle->totalDestinos()) }}</p>
        </div>
        @if (count($filtros->descripcion()) > 0)
            <div class="flex flex-wrap gap-2">
                @foreach ($filtros->descripcion() as $campo => $valor)
                    <span class="status-label">{{ $campo }}</span>
                @endforeach
            </div>
        @endif
        <div class="flex flex-wrap items-center gap-3">
            @if ($contexto->puedeVerPersonales)
                @php($integrantesUrl = route('reportes.pendientes-dependencia.integrantes', ['dependencia' => $detalle->codigo, ...$filtrosQuery]))
                <a href="{{ $integrantesUrl }}"
                   data-ver-integrantes
                   data-integrantes-url="{{ $integrantesUrl }}"
                   data-dependencia-nombre="{{ $detalle->nombre() }}"
                   class="inline-flex items-center gap-2 rounded-lg border border-emerald/40 bg-emerald/5 px-3 py-2 text-sm font-semibold text-emerald hover:bg-emerald/10"
                   title="Ver el padrón de integrantes activos y sus pendientes">
                    <x-icon name="users" class="size-4" />
                    Ver integrantes
                </a>
            @endif
            @can('reportes.exportar')
                <a href="{{ $exportarCsv }}"
                   class="inline-flex items-center gap-2 rounded-lg border border-emerald/40 bg-emerald/5 px-3 py-2 text-sm font-semibold text-emerald hover:bg-emerald/10"
                   title="Descargar los mismos destinos de esta vista, con los filtros aplicados">
                    <x-icon name="download" class="size-4" />
                    Exportar CSV
                </a>
            @endcan
            <a href="{{ $volver }}" class="text-sm font-semibold text-emerald">← Volver al resumen</a>
        </div>
    </div>

    <div class="mb-4 grid items-start gap-3 xl:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
    <x-evolucion-pendientes :publicacion="$detalle->publicacion->id" :puntos="$evolucion" :dias="$dias" />

    {{-- Gráfico de antigüedad (lo que el resumen no muestra) --}}
    <section aria-label="Antigüedad" class="rounded-xl border border-line bg-white p-4">
        <div class="mb-1 flex items-baseline justify-between">
            <h2 class="text-sm font-semibold text-ink">Antigüedad de los pendientes</h2>
            <span class="text-xs text-muted">Corte: {{ $detalle->fechaCorte->translatedFormat('d \d\e F \d\e Y') }}</span>
        </div>
        <p class="mb-3 text-xs text-muted">Días desde la recepción, o desde la emisión cuando aún no hay recepción.</p>
        <div class="space-y-1">
            @foreach (TramoAntiguedad::ordenados() as $tramo)
                @php($n = $detalle->distribucionAntiguedad[$tramo->value] ?? 0)
                <div class="flex flex-wrap items-center gap-3">
                    <span class="w-32 shrink-0 text-xs text-muted">{{ $tramo->etiqueta() }}</span>
                    <div class="flex h-6 flex-1 items-center">
                        <div class="h-2.5 rounded-full {{ $colorTramo[$tramo->value] }}"
                             style="width: {{ $n > 0 ? max(2, round($n / $maxTramo * 100)) : 0 }}%"
                             role="img" aria-label="{{ $n }} en {{ $tramo->etiqueta() }}"></div>
                    </div>
                    <span class="w-14 shrink-0 text-right text-sm font-semibold tabular-nums text-ink">{{ number_format($n) }}</span>
                </div>
            @endforeach
        </div>
    </section>

    </div>

    {{-- Tabla de asignaciones pendientes con identificación documental legible --}}
    <section aria-label="Asignaciones pendientes" class="rounded-2xl border border-line bg-white">
        <div class="border-b border-line px-6 py-4">
            <h2 class="text-sm font-semibold text-ink">Asignaciones pendientes</h2>
            @unless ($detalle->publicacion->tieneDatosDetalle())
                <p class="mt-1 text-xs text-amber-700">Publicación sin datos de integrantes: algunos campos documentales pueden no estar disponibles para este corte.</p>
            @endunless
        </div>
        @include('reportes.pendientes-dependencia.partials.tabla-destinos', [
            'filas' => $detalle->destinos,
            'corte' => $detalle->fechaCorte,
        ])
        @if ($detalle->destinos->hasPages())
            <div class="border-t border-line px-6 py-4">{{ $detalle->destinos->links() }}</div>
        @endif
    </section>

    {{-- Pie de calidad --}}
    <footer class="mt-10 flex flex-wrap gap-x-8 gap-y-2 border-t border-line pt-5 text-xs text-muted">
        <span><strong class="font-semibold text-ink">Publicación:</strong>
            {{ Carbon::parse($detalle->publicacion->fecha_publicacion)->translatedFormat('d \d\e F \d\e Y, H:i') }}</span>
        <span><strong class="font-semibold text-ink">Perfil:</strong> {{ ucfirst($detalle->publicacion->perfil) }}</span>
        <span><strong class="font-semibold text-ink">Unidad:</strong> asignaciones (destinos internos)</span>
    </footer>

    {{-- Modal de integrantes (§5). Un único <dialog> reutilizable: el padrón y el
         detalle por persona se cargan bajo demanda en [data-modal-cuerpo]. Sin JS,
         el enlace "Ver integrantes" navega al fragmento como fallback GET. --}}
    @if ($contexto->puedeVerPersonales)
        {{-- m-auto restaura el centrado nativo del <dialog> modal: el Preflight de
             Tailwind pone margin:0 y, sin él, showModal() lo deja en una esquina. --}}
        <dialog data-integrantes-dialog aria-labelledby="integrantes-titulo"
                class="m-auto w-full max-w-5xl rounded-2xl border border-line bg-white p-0 backdrop:bg-ink/40">
            <div class="flex items-center justify-between border-b border-line px-6 py-4">
                <h2 id="integrantes-titulo" class="text-sm font-semibold text-ink">Integrantes de {{ $detalle->nombre() }}</h2>
                <button type="button" data-modal-cerrar aria-label="Cerrar"
                        class="rounded-lg p-1 text-muted hover:bg-paper hover:text-ink">
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                        <path d="M5 5l10 10M15 5L5 15" stroke-linecap="round" />
                    </svg>
                </button>
            </div>
            <div class="max-h-[75vh] overflow-y-auto px-4 py-3">
                <p data-modal-estado role="status" aria-live="polite" class="sr-only"></p>
                <div data-modal-cuerpo></div>
            </div>
        </dialog>
    @endif
</x-layouts.platform>
