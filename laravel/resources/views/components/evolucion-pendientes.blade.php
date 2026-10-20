@props(['puntos', 'dias' => 30, 'subdependencias' => false, 'publicacion'])
@php
    $id = 'evolucion-'.Illuminate\Support\Str::uuid();
    $cantidad = count($puntos);
    $maximo = max(3, (int) ceil(max(array_column($puntos, 'total') ?: [0]) / 3) * 3);
    $inicio = $cantidad ? strtotime($puntos[0]['fecha']) : 0;
    $fin = $cantidad ? strtotime($puntos[$cantidad - 1]['fecha']) : 0;
    $coordenadas = [];
    $trazado = [];
    foreach ($puntos as $indice => $punto) {
        $x = $fin === $inicio ? 338 : 60 + (strtotime($punto['fecha']) - $inicio) / ($fin - $inicio) * 556;
        $y = 140 - $punto['total'] / $maximo * 100;
        $coordenadas[] = ['x' => round($x, 2), 'y' => round($y, 2)];
        $trazado[] = round($x, 2).','.round($y, 2);
    }
@endphp
<section class="min-w-0 rounded-xl border border-line bg-white p-4" aria-labelledby="{{ $id }}" data-evolucion>
    <div class="flex flex-wrap items-start justify-between gap-2">
        <div>
            <h2 id="{{ $id }}" class="section-heading"><x-icon name="chart" class="size-4 text-emerald" />Evolución de pendientes</h2>
            @if ($subdependencias)<p class="text-xs text-muted">Incluye las subdependencias según la estructura actual.</p>@endif
        </div>
        <form method="GET" action="{{ url()->current() }}" class="flex items-end gap-2" data-chart-controls>
            @foreach (request()->query() as $clave => $valor)
                @if (is_scalar($valor) && ! in_array($clave, ['publicacion', 'periodo', 'page', 'integrantes_page', 'persona_page'], true))
                    <input type="hidden" name="{{ $clave }}" value="{{ $valor }}">
                @endif
            @endforeach
            <input type="hidden" name="publicacion" value="{{ $publicacion }}">
            <div>
                <label for="{{ $id }}-periodo" class="sr-only">Período hasta el corte</label>
                <select id="{{ $id }}-periodo" name="periodo" class="field field-compact">
                    @foreach ([30, 60, 90] as $opcion)<option value="{{ $opcion }}" @selected($dias === $opcion)>{{ $opcion }} días</option>@endforeach
                </select>
            </div>
            <button class="btn btn-secondary btn-compact" type="submit">Ver</button>
        </form>
    </div>
    @if ($cantidad < 2)
        <div class="flex min-h-36 flex-col justify-center py-3 text-sm">
            <h3 class="font-medium">{{ $cantidad ? 'El histórico está comenzando' : 'Sin cortes en este período' }}</h3>
            <p class="mt-1 text-xs text-muted">Se necesitan al menos dos días con publicaciones comparables.</p>
            @if ($cantidad)<p class="mt-2 text-sm font-semibold text-emerald">{{ number_format($puntos[0]['total']) }} pendientes · {{ $puntos[0]['corte'] }}</p>@endif
        </div>
    @else
        <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-xs text-muted">
            <span class="inline-flex items-center gap-2"><span class="size-2 rounded-full bg-emerald" aria-hidden="true"></span>Pendientes</span>
            <span data-chart-value aria-live="polite">{{ $puntos[$cantidad - 1]['corte'] }} · {{ number_format($puntos[$cantidad - 1]['total']) }} pendientes</span>
        </div>
        <div class="mt-2 overflow-x-auto rounded-xl bg-paper/40">
            <svg viewBox="0 0 640 180" class="h-36 w-full min-w-0 text-emerald" preserveAspectRatio="none" role="group" aria-label="Evolución de pendientes por fecha de corte. Cada punto tiene una descripción; los valores también están en la tabla.">
                @foreach ([0, 1, 2, 3] as $paso)
                    <line x1="60" y1="{{ 140 - $paso * 100 / 3 }}" x2="616" y2="{{ 140 - $paso * 100 / 3 }}" stroke="#e2e8f0" stroke-dasharray="4 5" />
                    <text x="48" y="{{ 144 - $paso * 100 / 3 }}" text-anchor="end" fill="#526477" font-size="14">{{ number_format($maximo * $paso / 3, 0) }}</text>
                @endforeach
                <polyline points="{{ implode(' ', $trazado) }}" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linejoin="round" vector-effect="non-scaling-stroke" />
                @foreach ($puntos as $indice => $punto)
                    <circle cx="{{ $coordenadas[$indice]['x'] }}" cy="{{ $coordenadas[$indice]['y'] }}" r="3.5" fill="white" stroke="currentColor" stroke-width="2" tabindex="0" role="img"
                        aria-label="{{ $punto['corte'] }}: {{ number_format($punto['total']) }} pendientes"
                        data-chart-point="{{ $punto['corte'] }} · {{ number_format($punto['total']) }} pendientes">
                        <title>{{ $punto['corte'] }}: {{ number_format($punto['total']) }} pendientes</title>
                    </circle>
                @endforeach
                <text x="60" y="168" fill="#526477" font-size="14">{{ $puntos[0]['etiqueta'] }}</text>
                <text x="616" y="168" text-anchor="end" fill="#526477" font-size="14">{{ $puntos[$cantidad - 1]['etiqueta'] }}</text>
            </svg>
        </div>
    @endif
    @if ($cantidad)
        <details class="mt-2 border-t border-line pt-1">
            <summary class="inline-flex min-h-8 items-center gap-2 text-xs font-semibold text-emerald">Ver tabla de valores <x-icon name="reportes" class="size-4" /></summary>
            <p class="my-2 text-xs text-muted">Último corte diario en Lima, con los mismos filtros. La línea conecta los cortes disponibles; los días intermedios no añaden mediciones.</p>
            <div class="mt-2 max-h-64 overflow-auto">
                <table class="w-full text-sm">
                    <caption class="sr-only">Evolución de asignaciones pendientes, último corte de cada día en Lima</caption>
                    <thead><tr><th scope="col" class="px-3 py-2 text-left">Fecha de corte</th><th scope="col" class="px-3 py-2 text-right">Pendientes</th></tr></thead>
                    <tbody>@foreach ($puntos as $punto)<tr class="border-t border-line"><th scope="row" class="px-3 py-2 text-left font-normal">{{ $punto['corte'] }}</th><td class="px-3 py-2 text-right tabular-nums">{{ number_format($punto['total']) }}</td></tr>@endforeach</tbody>
                </table>
            </div>
        </details>
    @endif
</section>
