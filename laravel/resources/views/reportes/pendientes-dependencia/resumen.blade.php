@php
    use Illuminate\Support\Carbon;

    $foco = $nodoActual;
    $esInstitucional = $foco === null;

    // Por defecto cada dependencia muestra solo sus cifras propias (exclusivas);
    // con $acumulado se suman las de toda su descendencia. A nivel institucional
    // la suma de propias ya equivale al total, así que el modo no cambia las cifras.
    $usarAcumulado = $acumulado && ! $esInstitucional;

    if ($esInstitucional) {
        $tot = $resumen->totales();
        $entradaNoLeidos = $tot->entradaNoLeidos;
        $entradaRecibidos = $tot->entradaRecibidos;
        $delegadosNoLeidos = $tot->delegadosNoLeidos;
        $delegadosRecibidos = $tot->delegadosRecibidos;
        $pNormal = $tot->prioridadNormal; $pUrgente = $tot->prioridadUrgente; $pMuy = $tot->prioridadMuyUrgente;
        $hijas = $resumen->raices();
        $tituloFoco = 'Total institucional';
    } elseif ($usarAcumulado) {
        $entradaNoLeidos = $foco->accEntradaNoLeidos;
        $entradaRecibidos = $foco->accEntradaRecibidos;
        $delegadosNoLeidos = $foco->accDelegadosNoLeidos;
        $delegadosRecibidos = $foco->accDelegadosRecibidos;
        $pNormal = $foco->accPrioridadNormal; $pUrgente = $foco->accPrioridadUrgente; $pMuy = $foco->accPrioridadMuyUrgente;
        $hijas = $resumen->hijasDe($foco->codigo);
        $tituloFoco = "{$foco->codigo} · {$foco->nombre}";
    } else {
        $entradaNoLeidos = $foco->entradaNoLeidos;
        $entradaRecibidos = $foco->entradaRecibidos;
        $delegadosNoLeidos = $foco->delegadosNoLeidos;
        $delegadosRecibidos = $foco->delegadosRecibidos;
        $pNormal = $foco->prioridadNormal; $pUrgente = $foco->prioridadUrgente; $pMuy = $foco->prioridadMuyUrgente;
        $hijas = $resumen->hijasDe($foco->codigo);
        $tituloFoco = "{$foco->codigo} · {$foco->nombre}";
    }

    $totalFoco = $entradaNoLeidos + $entradaRecibidos + $delegadosNoLeidos + $delegadosRecibidos;

    // Cifras de cada hija según el modo: acumuladas o solo propias.
    $hEntrada = fn ($h) => $usarAcumulado ? $h->accEntradaTotal() : $h->entradaTotal();
    $hDelegados = fn ($h) => $usarAcumulado ? $h->accDelegadosTotal() : $h->delegadosTotal();
    $hTotal = fn ($h) => $usarAcumulado ? $h->accTotal() : $h->total();
    $maxHija = collect($hijas)->max($hTotal) ?: 1;

    // Preserva filtros y modo al navegar entre dependencias.
    $filtrosQuery = array_filter([
        'prioridad' => $filtros->prioridad,
        'estado' => $filtros->estadoRecepcion,
        'bandeja' => $filtros->bandeja,
        'publicacion' => $resumen->publicacion->id,
        'periodo' => request()->query('periodo'),
        'acumulado' => $acumulado ? '1' : null,
    ], fn ($valor) => $valor !== null && $valor !== '');
    $enlace = fn (?string $codigo, array $extra = []) => route('reportes.pendientes-dependencia',
        array_filter(['dependencia' => $codigo, ...$filtrosQuery, ...$extra], fn ($valor) => $valor !== null && $valor !== ''));

    // Enlaces para alternar el modo acumulado conservando el nodo y los filtros.
    $sinModo = array_diff_key($filtrosQuery, ['acumulado' => null]);
    $enlaceModo = fn (bool $acc) => route('reportes.pendientes-dependencia',
        array_filter(['dependencia' => $foco?->codigo, ...$sinModo, 'acumulado' => $acc ? '1' : null], fn ($valor) => $valor !== null && $valor !== ''));
@endphp

<x-layouts.platform :areas="$areas" :area="$area">
    <x-page-header icon="reportes" eyebrow="CATÁLOGO DE REPORTES" title="Pendientes por dependencia"
        description="Documentos no leídos y recibidos sin atender, en entrada y delegados, recorriendo la institución hasta el personal responsable." />

    {{-- Migas de pan del drill-down, con acceso al reporte general plano --}}
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <nav aria-label="Ruta de dependencias" class="flex flex-wrap items-center gap-2 text-sm">
            <a href="{{ $enlace(null) }}"
               class="{{ $esInstitucional ? 'font-semibold text-ink' : 'text-emerald hover:underline' }}">Institución</a>
            @unless ($esInstitucional)
                @foreach ($resumen->ruta($foco->codigo) as $paso)
                    <span class="text-slate-300" aria-hidden="true">/</span>
                    @if ($loop->last)
                        <span class="font-semibold text-ink">{{ $paso->nombre }}</span>
                    @else
                        <a href="{{ $enlace($paso->codigo) }}" class="text-emerald hover:underline">{{ $paso->nombre }}</a>
                    @endif
                @endforeach
            @endunless
        </nav>
        <a href="{{ route('reportes.pendientes-dependencia.general', array_diff_key($filtrosQuery, ['acumulado' => null])) }}"
           class="inline-flex items-center gap-1.5 text-sm font-semibold text-emerald hover:underline">
            Ver reporte general
            <span aria-hidden="true">→</span>
        </a>
    </div>

    <x-buscar-dependencia :publicacion="$resumen->publicacion->id" :busqueda="$busqueda" :resultados="$resultados" :rutas="$rutasBusqueda" :mostrar-resultados="true" :filtros="$filtros" />

    {{-- Panel del nodo en foco --}}
    <section aria-label="Resumen de {{ $tituloFoco }}" class="mb-4">
        <div class="mb-3 flex flex-wrap items-end justify-between gap-2">
            <div>
                <h2 class="text-sm font-semibold">{{ $tituloFoco }}</h2>
                @unless ($esInstitucional)
                    <p class="mt-1 text-xs text-muted">
                        {{ $usarAcumulado ? 'Incluye las cifras de sus subdependencias.' : 'Solo las cifras propias de esta dependencia.' }}
                    </p>
                @endunless
            </div>

        </div>

        {{-- Alternar modo de cifras y abrir el detalle de la dependencia --}}
        @unless ($esInstitucional)
            <div class="mb-3 flex flex-wrap items-center gap-2">
                <div class="inline-flex rounded-lg border border-line bg-white p-1 text-xs font-medium" role="group" aria-label="Modo de cifras">
                    <a href="{{ $enlaceModo(false) }}"
                       @if (! $usarAcumulado) aria-current="true" @endif
                       class="rounded-md px-3 py-1.5 {{ ! $usarAcumulado ? 'bg-emerald text-white' : 'text-muted hover:text-ink' }}">Solo esta dependencia</a>
                    <a href="{{ $enlaceModo(true) }}"
                       @if ($usarAcumulado) aria-current="true" @endif
                       class="rounded-md px-3 py-1.5 {{ $usarAcumulado ? 'bg-emerald text-white' : 'text-muted hover:text-ink' }}">Con subdependencias</a>
                </div>
                <a href="{{ route('reportes.pendientes-dependencia.detalle', [$foco->codigo, ...$sinModo]) }}"
                   class="btn btn-secondary text-xs">Ver detalle de destinos ↗</a>
            </div>
        @endunless

        <div class="grid grid-cols-2 gap-3 md:grid-cols-[minmax(140px,.65fr)_1fr_1fr]">
            <div class="col-span-2 rounded-xl border border-line bg-white p-4 md:col-span-1">
                <p class="text-xs font-medium text-muted">Total de asignaciones pendientes</p>
                <p class="mt-2 text-3xl font-semibold tracking-tight tabular-nums">{{ number_format($totalFoco) }}</p>
                <p class="mt-2 text-xs text-muted">{{ $usarAcumulado ? 'Con subdependencias' : 'Cifras propias' }}</p>
            </div>
            <x-kpi-pendientes titulo="Entrada" descripcion="Dirigidos al encargado de la dependencia."
                :no-leidos="$entradaNoLeidos" :recibidos="$entradaRecibidos" />
            <x-kpi-pendientes titulo="Delegados" descripcion="Dirigidos a un empleado distinto del encargado."
                :no-leidos="$delegadosNoLeidos" :recibidos="$delegadosRecibidos" />
        </div>

    </section>

    <div class="mb-4 grid items-start gap-3 xl:grid-cols-[minmax(0,2fr)_minmax(260px,1fr)]">
        <x-evolucion-pendientes :publicacion="$resumen->publicacion->id" :puntos="$evolucion" :dias="$dias" :subdependencias="$usarAcumulado" />
        <section class="h-full rounded-xl border border-line bg-white p-4" aria-label="Prioridades">
            <h2 class="mb-4 text-sm font-semibold">Distribución por prioridad</h2>
            <x-barra-prioridad :leyenda="false" :normal="$pNormal" :urgente="$pUrgente" :muy-urgente="$pMuy" />
            <dl class="mt-3 divide-y divide-line text-sm">
                @foreach ([['Normal', $pNormal, 'bg-blue-600'], ['Urgente', $pUrgente, 'bg-orange-500'], ['Muy urgente', $pMuy, 'bg-red-600']] as [$nombrePrioridad, $cantidadPrioridad, $colorPrioridad])
                    <div class="flex items-center justify-between gap-3 py-3">
                        <dt class="flex items-center gap-2 text-muted"><span class="size-2 rounded-full {{ $colorPrioridad }}" aria-hidden="true"></span>{{ $nombrePrioridad }}</dt>
                        <dd class="flex items-center gap-3 tabular-nums"><span class="font-semibold">{{ number_format($cantidadPrioridad) }}</span><span class="w-12 text-right text-xs text-muted">{{ $totalFoco ? round($cantidadPrioridad / $totalFoco * 100) : 0 }}%</span></dd>
                    </div>
                @endforeach
            </dl>
        </section>
    </div>

    {{-- Tabla navegable de dependencias hijas --}}
    @if (count($hijas) > 0)
        <section aria-label="Dependencias" class="rounded-xl border border-line bg-white" data-dynamic-table>
            <div class="flex items-center justify-between border-b border-line px-4 py-2.5">
                <h2 class="text-sm font-semibold text-ink">
                    {{ $esInstitucional ? 'Dependencias principales' : 'Dependencias dentro de ' . $foco->nombre }}
                </h2>
                <span class="status-label">{{ $usarAcumulado ? 'Acumulado con descendencia' : 'Cifras propias' }}</span>
            </div>
            <div class="table-scroll overflow-x-auto">
                <table class="w-full min-w-[720px] text-sm">
                    <thead>
                        <tr class="border-b border-line text-left text-xs uppercase tracking-wide text-muted">
                            <th data-sort-type="text" scope="col" class="px-6 py-3 font-semibold">Dependencia</th>
                            <th data-sort-type="number" scope="col" class="px-4 py-3 text-right font-semibold">Entrada</th>
                            <th data-sort-type="number" scope="col" class="px-4 py-3 text-right font-semibold">Delegados</th>
                            <th data-sort-type="number" scope="col" class="px-4 py-3 text-right font-semibold">Total</th>
                            <th data-sort-type="none" scope="col" class="px-6 py-3 font-semibold">Magnitud</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($hijas as $h)
                            @php($tieneHijas = count($h->hijas) > 0)
                            <tr class="border-b border-line/70 last:border-0 hover:bg-paper/60">
                                <th scope="row" class="px-4 py-2.5 text-left font-normal">
                                    <a href="{{ $enlace($h->codigo) }}"
                                       class="group inline-flex items-center gap-2 font-medium text-ink hover:text-emerald">
                                        <span class="font-mono text-xs text-muted">{{ $h->codigo }}</span>
                                        <span>{{ $h->nombre }}</span>
                                        <span aria-hidden="true" class="text-emerald opacity-0 transition group-hover:opacity-100">↗</span>
                                    </a>
                                    @unless ($h->activa)
                                        <span class="ml-2 rounded bg-slate-100 px-1.5 py-0.5 text-xs text-muted">inactiva</span>
                                    @endunless
                                    @if ($tieneHijas)
                                        <p class="mt-0.5 text-xs text-muted">{{ count($h->hijas) }} subdependencia(s)</p>
                                    @endif
                                </th>
                                <td class="px-4 py-2.5 text-right tabular-nums">{{ number_format($hEntrada($h)) }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums">{{ number_format($hDelegados($h)) }}</td>
                                <td class="px-4 py-2.5 text-right font-semibold tabular-nums">{{ number_format($hTotal($h)) }}</td>
                                <td class="px-4 py-2.5">
                                    <div class="h-2 w-full overflow-hidden rounded-full bg-paper" role="img"
                                         aria-label="{{ $hTotal($h) }} pendientes">
                                        <span class="block h-full rounded-full bg-emerald"
                                              style="width: {{ $hTotal($h) > 0 ? max(2, round($hTotal($h) / $maxHija * 100)) : 0 }}%"></span>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @else
        <x-alert class="mt-2">
            <strong class="font-semibold">Nivel de personal.</strong>
            Esta dependencia no tiene subdependencias. Sus cifras corresponden a los destinos dirigidos a su encargado y a los empleados delegados.
        </x-alert>
    @endif

    {{-- Pie de calidad: fecha, perfil y versión siempre visibles --}}
    <footer class="mt-4 flex flex-wrap gap-x-8 gap-y-2 border-t border-line pt-5 text-xs text-muted">
        <span><strong class="font-semibold text-ink">Publicación:</strong>
            {{ Carbon::parse($resumen->publicacion->fecha_publicacion)->translatedFormat('d \d\e F \d\e Y, H:i') }}</span>
        <span><strong class="font-semibold text-ink">Perfil:</strong> {{ ucfirst($resumen->publicacion->perfil) }}</span>
        <span><strong class="font-semibold text-ink">Versión de reglas:</strong> {{ $resumen->publicacion->version_reglas }}</span>
        <span><strong class="font-semibold text-ink">Unidad:</strong> asignaciones (destinos internos)</span>
        @if ($resumen->publicacion->nota)
            <span class="w-full text-xs italic">{{ $resumen->publicacion->nota }}</span>
        @endif
    </footer>
</x-layouts.platform>
