@php
    use Illuminate\Support\Carbon;

    $filtrosQuery = array_filter([
        'prioridad' => $filtros->prioridad,
        'estado' => $filtros->estadoRecepcion,
        'bandeja' => $filtros->bandeja,
        'buscar' => $busqueda,
        'publicacion' => $resumen->publicacion->id,
        'periodo' => request()->query('periodo'),
    ], fn ($valor) => $valor !== null && $valor !== '');
    $exportarCsv = route('reportes.pendientes-dependencia.general.exportar', $filtrosQuery);
    $exportarXlsx = route('reportes.pendientes-dependencia.general.exportar', [...$filtrosQuery, 'formato' => 'xlsx']);

    $dependencias = $exportacion->dependenciasOrdenadas();
    $totales = $resumen->totales();

    // Convención del reporte: azul normal, naranja urgente, rojo muy urgente.
    // El color acompaña SIEMPRE a la etiqueta de texto, nunca va solo.
    $prioridadColor = ['normal' => 'bg-blue-600', 'urgente' => 'bg-orange-500', 'muy' => 'bg-red-600'];
@endphp

<x-layouts.platform :areas="$areas" :area="$area">
    <x-page-header icon="reportes" eyebrow="CATÁLOGO DE REPORTES · GENERAL"
        title="Pendientes por dependencia — general"
        description="Consulta las cifras propias de cada oficina, busca una dependencia y exporta los resultados a Excel." />

    {{-- Migas de pan --}}
    <nav aria-label="Ruta" class="mb-3 flex flex-wrap items-center gap-2 text-sm">
        <a href="{{ route('reportes.pendientes-dependencia', array_diff_key($filtrosQuery, ['buscar' => null])) }}" class="text-emerald hover:underline">Resumen jerárquico</a>
        <span class="text-slate-300" aria-hidden="true">/</span>
        <span class="font-semibold text-ink">{{ $busqueda === null ? 'General (todas las dependencias)' : 'General · resultados de búsqueda' }}</span>
    </nav>

    <x-buscar-dependencia :publicacion="$resumen->publicacion->id" :busqueda="$busqueda" :filtros="$filtros" />

    {{-- Barra de contexto y exportación --}}
    <div class="mb-4 flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-line bg-white px-4 py-3">
        <div class="flex flex-wrap items-baseline gap-x-8 gap-y-1">
            <div>
                <p class="text-xs text-muted">Dependencias</p>
                <p class="text-2xl font-light tabular-nums text-ink">{{ number_format(count($dependencias)) }}</p>
            </div>
            <div>
                <p class="text-xs text-muted">Total de destinos pendientes</p>
                <p class="text-2xl font-light tabular-nums text-ink">{{ number_format($totales->total()) }}</p>
            </div>
        </div>
        @can('reportes.exportar')
            <div class="flex items-center gap-3">
                <a href="{{ $exportarXlsx }}"
                   class="inline-flex items-center gap-2 rounded-lg bg-emerald px-4 py-2 text-sm font-semibold text-white hover:bg-emerald/90"
                   title="Descargar todas las dependencias en un archivo Excel (.xlsx)">
                    <x-icon name="download" class="size-4" />
                    Exportar Excel
                </a>
                <a href="{{ $exportarCsv }}"
                   class="inline-flex items-center gap-2 rounded-lg border border-emerald/40 bg-emerald/5 px-3 py-2 text-sm font-semibold text-emerald hover:bg-emerald/10"
                   title="Descargar todas las dependencias en CSV">
                    CSV
                </a>
            </div>
        @endcan
    </div>

    {{-- Tabla plana: todas las dependencias --}}
    <section aria-label="Dependencias" class="rounded-xl border border-line bg-white" data-dynamic-table>
        <div class="table-scroll overflow-x-auto">
            <table class="w-full min-w-[920px] text-sm">
                <thead>
                    <tr class="border-b border-line text-left text-xs uppercase tracking-wide text-muted">
                        <th data-sort-type="text" scope="col" class="px-6 py-3 font-semibold">Dependencia</th>
                        <th data-sort-type="number" scope="col" class="px-3 py-3 text-right font-semibold">Entrada<br><span class="font-normal normal-case">no leídos</span></th>
                        <th data-sort-type="number" scope="col" class="px-3 py-3 text-right font-semibold">Entrada<br><span class="font-normal normal-case">recibidos</span></th>
                        <th data-sort-type="number" scope="col" class="px-3 py-3 text-right font-semibold">Delegados<br><span class="font-normal normal-case">no leídos</span></th>
                        <th data-sort-type="number" scope="col" class="px-3 py-3 text-right font-semibold">Delegados<br><span class="font-normal normal-case">recibidos</span></th>
                        <th data-sort-type="number" scope="col" class="px-4 py-3 text-right font-semibold">Total</th>
                        <th data-sort-type="none" scope="col" class="px-4 py-3 text-right font-semibold">Prioridades <span class="font-normal normal-case">(N · U · MU)</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($dependencias as $fila)
                        <tr class="border-b border-line/70 last:border-0 hover:bg-paper/60 {{ $fila->total() === 0 ? 'text-muted' : '' }}">
                            <th scope="row" class="px-6 py-3 text-left font-normal">
                                <a href="{{ route('reportes.pendientes-dependencia.detalle', ['dependencia' => $fila->codigo, ...$filtrosQuery]) }}"
                                   class="hover:underline">
                                    <span class="font-mono text-xs text-muted">{{ $fila->codigo }}</span>
                                    <span class="ml-2 text-ink">{{ $fila->nombre }}</span>
                                </a>
                            </th>
                            <td class="px-3 py-3 text-right tabular-nums">{{ number_format($fila->entradaNoLeidos) }}</td>
                            <td class="px-3 py-3 text-right tabular-nums">{{ number_format($fila->entradaRecibidos) }}</td>
                            <td class="px-3 py-3 text-right tabular-nums">{{ number_format($fila->delegadosNoLeidos) }}</td>
                            <td class="px-3 py-3 text-right tabular-nums">{{ number_format($fila->delegadosRecibidos) }}</td>
                            <td class="px-4 py-3 text-right font-semibold tabular-nums text-ink">{{ number_format($fila->total()) }}</td>
                            <td class="px-4 py-3 text-right">
                                <span class="inline-flex items-center gap-2 text-xs tabular-nums">
                                    <span class="inline-flex items-center gap-1" title="Normal">
                                        <span class="inline-block h-2 w-2 rounded-sm {{ $prioridadColor['normal'] }}" aria-hidden="true"></span>{{ $fila->prioridadNormal }}
                                    </span>
                                    <span class="inline-flex items-center gap-1" title="Urgente">
                                        <span class="inline-block h-2 w-2 rounded-sm {{ $prioridadColor['urgente'] }}" aria-hidden="true"></span>{{ $fila->prioridadUrgente }}
                                    </span>
                                    <span class="inline-flex items-center gap-1" title="Muy urgente">
                                        <span class="inline-block h-2 w-2 rounded-sm {{ $prioridadColor['muy'] }}" aria-hidden="true"></span>{{ $fila->prioridadMuyUrgente }}
                                    </span>
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-6 py-10 text-center text-muted">No hay dependencias que coincidan con la búsqueda.</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-line bg-paper/40 font-semibold text-ink">
                        <th scope="row" class="px-6 py-3 text-left">{{ $busqueda === null ? 'Total institucional' : 'Total de resultados' }}</th>
                        <td class="px-3 py-3 text-right tabular-nums">{{ number_format($totales->entradaNoLeidos) }}</td>
                        <td class="px-3 py-3 text-right tabular-nums">{{ number_format($totales->entradaRecibidos) }}</td>
                        <td class="px-3 py-3 text-right tabular-nums">{{ number_format($totales->delegadosNoLeidos) }}</td>
                        <td class="px-3 py-3 text-right tabular-nums">{{ number_format($totales->delegadosRecibidos) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format($totales->total()) }}</td>
                        <td class="px-4 py-3 text-right text-xs tabular-nums">
                            {{ $totales->prioridadNormal }} · {{ $totales->prioridadUrgente }} · {{ $totales->prioridadMuyUrgente }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>

    {{-- Leyenda de prioridades: color + texto, nunca color solo --}}
    <div class="mt-4 flex flex-wrap items-center gap-4 text-xs text-muted">
        <span class="font-semibold">Prioridades:</span>
        <span class="inline-flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-sm bg-blue-600" aria-hidden="true"></span>Normal (N)</span>
        <span class="inline-flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-sm bg-orange-500" aria-hidden="true"></span>Urgente (U)</span>
        <span class="inline-flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-sm bg-red-600" aria-hidden="true"></span>Muy urgente (MU)</span>
    </div>

    {{-- Pie de calidad --}}
    <footer class="mt-10 flex flex-wrap gap-x-8 gap-y-2 border-t border-line pt-5 text-xs text-muted">
        <span><strong class="font-semibold text-ink">Publicación:</strong>
            {{ Carbon::parse($resumen->publicacion->fecha_publicacion)->translatedFormat('d \d\e F \d\e Y, H:i') }}</span>
        <span><strong class="font-semibold text-ink">Perfil:</strong> {{ ucfirst($resumen->publicacion->perfil) }}</span>
        <span><strong class="font-semibold text-ink">Unidad:</strong> asignaciones (destinos internos)</span>
        <span><strong class="font-semibold text-ink">Alcance:</strong> cifras propias, sin jerarquía</span>
    </footer>
</x-layouts.platform>
