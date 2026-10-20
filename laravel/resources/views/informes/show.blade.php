@php
    use Illuminate\Support\Carbon;

    $d = $informe->evidencia;
    $textos = $d['textos'] ?? ['resumen' => '', 'conclusiones' => [], 'recomendaciones' => []];
    $resumen = $d['resumen'] ?? null;

    $n = fn ($v) => number_format((int) $v);
    $pct = fn ($v) => number_format((float) $v, 1) . '%';
@endphp

<x-layouts.platform :areas="$areas" :area="$area">
    <x-page-header icon="informes" eyebrow="INFORMES INTELIGENTES · BETA" :title="$informe->numero ?: 'Informe de pendientes'"
        :description="'Alcance: ' . $informe->alcance() . '. Las cifras provienen de agregados verificados del motor; requiere revisión humana.'" />

    @if (session('estado'))
        <x-alert class="mb-6">{{ session('estado') }}</x-alert>
    @endif

    {{-- Barra de estado y acciones --}}
    <div class="mb-8 flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-line bg-white px-6 py-5">
        <div class="flex items-center gap-4">
            <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $informe->esAprobado() ? 'bg-emerald/10 text-emerald' : 'bg-amber-100 text-amber-700' }}">
                {{ $informe->esAprobado() ? 'Aprobado' : 'Borrador' }}
            </span>
            @if ($informe->esAprobado() && $informe->aprobador)
                <span class="text-xs text-muted">
                    Aprobado por {{ $informe->aprobador->nombre_completo }},
                    {{ Carbon::parse($informe->aprobado_en)->translatedFormat('d \d\e F \d\e Y, H:i') }}
                </span>
            @endif
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('informes.descargar', $informe) }}"
               class="inline-flex items-center gap-2 rounded-lg bg-emerald px-4 py-2 text-sm font-semibold text-white hover:bg-emerald/90">
                <x-icon name="download" class="size-4" />
                Descargar Word
            </a>
            @if (! $informe->esAprobado() && $puedeAprobar)
                <form method="POST" action="{{ route('informes.aprobar', $informe) }}"
                      onsubmit="return confirm('¿Aprobar este informe tras la revisión? Quedará cerrado.');">
                    @csrf
                    <button type="submit" class="btn btn-secondary">Aprobar</button>
                </form>
            @elseif (! $informe->esAprobado())
                <span class="text-xs text-muted">Requiere permiso de aprobación</span>
            @endif
        </div>
    </div>

    {{-- Vista previa del contenido redactado --}}
    <article class="space-y-8 rounded-2xl border border-line bg-white p-6 sm:p-8">
        <section>
            <h2 class="text-sm font-bold uppercase tracking-wide text-ink">Resumen general</h2>
            <p class="mt-3 text-sm leading-7 text-ink">{{ $textos['resumen'] }}</p>

            @if ($resumen)
                <div class="mt-5 overflow-x-auto">
                    <table class="w-full min-w-[640px] text-sm">
                        <thead>
                            <tr class="bg-[#1F3864] text-left text-xs font-semibold text-white">
                                <th class="px-4 py-2">Nivel</th>
                                <th class="px-4 py-2 text-right">Total</th>
                                <th class="px-4 py-2 text-right">No leídos</th>
                                <th class="px-4 py-2 text-right">% No leídos</th>
                                <th class="px-4 py-2 text-right">Sin atender</th>
                                <th class="px-4 py-2 text-right">Urgentes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($d['por_nivel'] as $fila)
                                <tr class="border-b border-line/70">
                                    <td class="px-4 py-2">{{ $fila['nombre'] }}</td>
                                    <td class="px-4 py-2 text-right tabular-nums">{{ $n($fila['total']) }}</td>
                                    <td class="px-4 py-2 text-right tabular-nums">{{ $n($fila['no_leidos']) }}</td>
                                    <td class="px-4 py-2 text-right tabular-nums">{{ $pct($fila['pct_no_leidos']) }}</td>
                                    <td class="px-4 py-2 text-right tabular-nums">{{ $n($fila['sin_atender']) }}</td>
                                    <td class="px-4 py-2 text-right tabular-nums">{{ $n($fila['urgentes']) }}</td>
                                </tr>
                            @endforeach
                            <tr class="bg-[#1F3864] font-semibold text-white">
                                <td class="px-4 py-2">TOTAL GENERAL</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ $n($resumen['total']) }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ $n($resumen['no_leidos']) }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ $pct($resumen['pct_no_leidos']) }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ $n($resumen['sin_atender']) }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ $n($resumen['urgentes']) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        {{-- Ranking (primeras 10 en la vista previa; el Word trae todas) --}}
        @if (! empty($d['ranking']))
            <section>
                <h2 class="text-sm font-bold uppercase tracking-wide text-ink">Ranking por total de pendientes</h2>
                <p class="mt-1 text-xs text-muted">Se muestran las 10 primeras; el documento Word incluye todas.</p>
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full min-w-[640px] text-sm">
                        @php
                            $maxRank = max(1, collect($d['ranking'])->max('total'));
                        @endphp
                        <thead>
                            {{-- Cabecera agrupada: ENTRADA y DELEGADOS, cada una con No leído / Recibidos sin atender.
                                 El Total va tras los grupos, antes de Urgentes. --}}
                            <tr class="text-xs uppercase tracking-wide text-muted">
                                <th rowspan="2" class="border-b border-line px-3 py-2 text-left align-bottom">#</th>
                                <th rowspan="2" class="border-b border-line px-3 py-2 text-left align-bottom">Unidad orgánica</th>
                                <th colspan="2" class="border-b border-l border-line px-3 py-1 text-center">Entrada</th>
                                <th colspan="2" class="border-b border-l border-line px-3 py-1 text-center">Delegados</th>
                                <th rowspan="2" class="border-b border-l border-line px-3 py-2 text-right align-bottom">Total</th>
                                <th rowspan="2" class="border-b border-l border-line px-3 py-2 text-right align-bottom">Urgentes</th>
                            </tr>
                            <tr class="text-xs uppercase tracking-wide text-muted">
                                <th class="border-b border-l border-line px-3 py-1 text-right font-normal">No leído</th>
                                <th class="border-b border-line px-3 py-1 text-right font-normal">Recibidos sin atender</th>
                                <th class="border-b border-l border-line px-3 py-1 text-right font-normal">No leído</th>
                                <th class="border-b border-line px-3 py-1 text-right font-normal">Recibidos sin atender</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (array_slice($d['ranking'], 0, 10) as $i => $fila)
                                @php
                                    // Escala rojo→verde del total (rojo = más backlog); el número va siempre.
                                    $t = min(1, $fila['total'] / $maxRank);
                                    $hue = (1 - $t) * 120; // 0=rojo, 120=verde
                                    $bg = "hsl({$hue}deg 70% 45%)";
                                @endphp
                                <tr class="border-b border-line/70">
                                    <td class="px-3 py-2 tabular-nums">{{ $i + 1 }}</td>
                                    <td class="px-3 py-2">{{ $fila['nombre'] }}</td>
                                    <td class="border-l border-line/50 px-3 py-2 text-right tabular-nums">{{ $n($fila['entrada_no_leidos'] ?? 0) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ $n($fila['entrada_sin_atender'] ?? 0) }}</td>
                                    <td class="border-l border-line/50 px-3 py-2 text-right tabular-nums">{{ $n($fila['delegados_no_leidos'] ?? 0) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ $n($fila['delegados_sin_atender'] ?? 0) }}</td>
                                    <td class="border-l border-line/50 px-3 py-2 text-right">
                                        <span class="inline-block min-w-[2.75rem] rounded px-2 py-0.5 text-center font-semibold tabular-nums text-white"
                                              style="background: {{ $bg }}">{{ $n($fila['total']) }}</span>
                                    </td>
                                    <td class="border-l border-line/50 px-3 py-2 text-right tabular-nums">{{ $n($fila['urgentes']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        <section>
            <h2 class="text-sm font-bold uppercase tracking-wide text-ink">Conclusiones</h2>
            <ul class="mt-3 list-disc space-y-1.5 pl-5 text-sm leading-7 text-ink">
                @foreach ($textos['conclusiones'] as $c)<li>{{ $c }}</li>@endforeach
            </ul>
        </section>

        <section>
            <h2 class="text-sm font-bold uppercase tracking-wide text-ink">Recomendaciones</h2>
            <ul class="mt-3 list-disc space-y-1.5 pl-5 text-sm leading-7 text-ink">
                @foreach ($textos['recomendaciones'] as $r)<li>{{ $r }}</li>@endforeach
            </ul>
        </section>
    </article>

    {{-- Pie de trazabilidad --}}
    <footer class="mt-8 flex flex-wrap gap-x-8 gap-y-2 border-t border-line pt-5 text-xs text-muted">
        <span><strong class="font-semibold text-ink">Publicación:</strong> #{{ $informe->publicacion_id }}</span>
        <span><strong class="font-semibold text-ink">Redacción:</strong> plantilla determinista (sin IA)</span>
        <span><strong class="font-semibold text-ink">Cifras:</strong> agregados verificados del motor</span>
        <a href="{{ route('informes.index') }}" class="font-semibold text-emerald">← Volver a informes</a>
    </footer>
</x-layouts.platform>
