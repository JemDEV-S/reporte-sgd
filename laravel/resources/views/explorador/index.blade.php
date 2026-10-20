@php
    use Illuminate\Support\Carbon;

    // Parámetros actuales, para conservar la consulta al guardar y al enlazar.
    $params = $consulta->aArray();
    // Selección actual de agrupaciones y filtros, para marcar el formulario.
    $agrupSel = $consulta->agrupaciones;
    $filtrosSel = $consulta->filtros;
@endphp

<x-layouts.platform :areas="$areas" :area="$area">
    <x-page-header eyebrow="EXPLORADOR DE DATOS · BETA" title="Explorador de datos"
        description="Agrupa y filtra los destinos pendientes de la publicación vigente. La medida es el conteo de asignaciones; las cifras comparten las reglas del reporte." />

    @if (session('estado'))
        <x-alert class="mb-6">{{ session('estado') }}</x-alert>
    @endif

    <div class="grid gap-8 lg:grid-cols-[320px_1fr]">
        {{-- Panel de configuración de la consulta --}}
        <aside class="space-y-6">
            <form method="GET" action="{{ route('explorador.index') }}" class="rounded-2xl border border-line bg-white p-5">
                <h2 class="text-sm font-semibold text-ink">Consulta</h2>

                {{-- Agrupar por: dimensiones permitidas (lista cerrada) --}}
                <fieldset class="mt-4">
                    <legend class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-muted">Agrupar por</legend>
                    <div class="space-y-2">
                        @foreach ($campos as $campo)
                            @if ($campo->agrupable)
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" name="agrupaciones[]" value="{{ $campo->clave }}"
                                        @checked(in_array($campo->clave, $agrupSel, true))
                                        class="h-4 w-4 rounded border-slate-300 text-emerald focus:ring-emerald">
                                    {{ $campo->etiqueta }}
                                </label>
                            @endif
                        @endforeach
                    </div>
                    <p class="mt-2 text-[11px] text-muted">Sin agrupaciones se muestra el total del universo filtrado.</p>
                </fieldset>

                {{-- Filtros de lista cerrada --}}
                <fieldset class="mt-5">
                    <legend class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-muted">Filtros</legend>
                    <div class="space-y-3">
                        @foreach ($campos as $campo)
                            @if ($campo->filtrable && $campo->opciones !== null)
                                <div>
                                    <label for="f-{{ $campo->clave }}" class="mb-1 block text-xs text-muted">{{ $campo->etiqueta }}</label>
                                    <select id="f-{{ $campo->clave }}" name="filtros[{{ $campo->clave }}]" class="field">
                                        <option value="">Todos</option>
                                        @foreach ($campo->opciones as $valor => $etiqueta)
                                            <option value="{{ $valor }}" @selected(($filtrosSel[$campo->clave] ?? null) === (string) $valor)>{{ $etiqueta }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                        @endforeach

                        {{-- Dependencia exacta (opcional) --}}
                        <div>
                            <label for="f-dependencia" class="mb-1 block text-xs text-muted">Código de dependencia</label>
                            <input id="f-dependencia" name="dependencia" value="{{ $consulta->dependencia }}"
                                placeholder="p. ej. 00021" maxlength="10" class="field">
                        </div>
                    </div>
                </fieldset>

                {{-- Orden --}}
                <div class="mt-5">
                    <label for="orden" class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-muted">Orden</label>
                    <select id="orden" name="orden" class="field">
                        <option value="cantidad_desc" @selected($consulta->orden === 'cantidad_desc')>Mayor conteo primero</option>
                        <option value="cantidad_asc" @selected($consulta->orden === 'cantidad_asc')>Menor conteo primero</option>
                        <option value="etiqueta" @selected($consulta->orden === 'etiqueta')>Por etiqueta</option>
                    </select>
                </div>

                <div class="mt-6 flex gap-2">
                    <button type="submit" class="btn btn-primary flex-1">Ejecutar</button>
                    <a href="{{ route('explorador.index') }}" class="btn btn-secondary">Limpiar</a>
                </div>
            </form>

            {{-- Consultas guardadas --}}
            <div class="rounded-2xl border border-line bg-white p-5">
                <h2 class="text-sm font-semibold text-ink">Consultas guardadas</h2>
                @if ($guardadas->isEmpty())
                    <p class="mt-3 text-xs text-muted">Aún no has guardado consultas. Ejecuta una y guárdala para reutilizarla.</p>
                @else
                    <ul class="mt-3 space-y-2">
                        @foreach ($guardadas as $g)
                            <li class="flex items-center justify-between gap-2 text-sm">
                                <a href="{{ route('explorador.cargar', $g) }}" class="truncate text-emerald hover:underline" title="{{ $g->nombre }}">
                                    {{ $g->nombre }}
                                </a>
                                <span class="shrink-0 text-[10px] text-muted">
                                    {{ $g->esInstitucional() ? 'Institucional' : 'Privada' }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </aside>

        {{-- Resultado --}}
        <section aria-label="Resultado" class="space-y-6">
            {{-- Resumen de la consulta y guardar --}}
            <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-line bg-white px-6 py-5">
                <div>
                    <p class="text-xs text-muted">Total de destinos en el universo filtrado</p>
                    <p class="text-3xl font-light tabular-nums text-ink">{{ number_format($resultado->total) }}</p>
                </div>
                <form method="POST" action="{{ route('explorador.guardar', $params) }}" class="flex flex-wrap items-end gap-2">
                    @csrf
                    <div>
                        <label for="nombre" class="mb-1 block text-[11px] font-semibold text-muted">Guardar esta consulta</label>
                        <input id="nombre" name="nombre" required maxlength="120" placeholder="Nombre de la consulta" class="field w-56">
                    </div>
                    <select name="visibilidad" class="field w-36" aria-label="Visibilidad">
                        <option value="privada">Privada</option>
                        <option value="institucional">Institucional</option>
                    </select>
                    <button type="submit" class="btn btn-secondary">Guardar</button>
                </form>
            </div>

            {{-- Tabla de resultados --}}
            <div class="rounded-2xl border border-line bg-white">
                <div class="border-b border-line px-6 py-4">
                    <h2 class="text-sm font-semibold text-ink">
                        @if ($resultado->tieneAgrupaciones())
                            Conteo por {{ implode(' · ', $resultado->columnas) }}
                        @else
                            Total del universo
                        @endif
                    </h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[480px] text-sm">
                        <thead>
                            <tr class="border-b border-line text-left text-[11px] uppercase tracking-wide text-muted">
                                @foreach ($resultado->columnas as $col)
                                    <th scope="col" class="px-6 py-3 font-semibold">{{ $col }}</th>
                                @endforeach
                                <th scope="col" class="px-6 py-3 text-right font-semibold">Destinos</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php($max = $resultado->maximo())
                            @forelse ($resultado->filas as $fila)
                                <tr class="border-b border-line/70 last:border-0 hover:bg-paper/60">
                                    @foreach ($fila['dimensiones'] as $dim)
                                        <td class="px-6 py-3 text-ink">{{ $dim['valor'] }}</td>
                                    @endforeach
                                    @if (! $resultado->tieneAgrupaciones())
                                        <td class="px-6 py-3 text-ink">Todos los pendientes</td>
                                    @endif
                                    <td class="px-6 py-3">
                                        <div class="flex items-center justify-end gap-3">
                                            {{-- Barra de magnitud; el número acompaña siempre a la barra --}}
                                            <div class="hidden h-2 w-32 overflow-hidden rounded-full bg-paper sm:block" aria-hidden="true">
                                                <div class="h-full rounded-full bg-emerald" style="width: {{ max(2, round($fila['cantidad'] / $max * 100)) }}%"></div>
                                            </div>
                                            <span class="w-16 text-right font-semibold tabular-nums text-ink">{{ number_format($fila['cantidad']) }}</span>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="{{ count($resultado->columnas) + 1 }}" class="px-6 py-10 text-center text-sm text-muted">
                                    No hay destinos con los filtros aplicados.
                                </td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Pie de calidad --}}
            <footer class="flex flex-wrap gap-x-8 gap-y-2 border-t border-line pt-5 text-xs text-muted">
                <span><strong class="font-semibold text-ink">Publicación:</strong>
                    {{ Carbon::parse($resultado->publicacion->fecha_publicacion)->translatedFormat('d \d\e F \d\e Y, H:i') }}</span>
                <span><strong class="font-semibold text-ink">Universo:</strong> pendientes (tipo 01, recepción 0/1)</span>
                <span><strong class="font-semibold text-ink">Medida:</strong> conteo de destinos</span>
            </footer>
        </section>
    </div>
</x-layouts.platform>
