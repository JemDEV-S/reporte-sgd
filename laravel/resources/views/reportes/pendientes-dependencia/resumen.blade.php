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
        'acumulado' => $acumulado ? '1' : null,
    ]);
    $enlace = fn (?string $codigo, array $extra = []) => route('reportes.pendientes-dependencia',
        array_filter(['dependencia' => $codigo, ...$filtrosQuery, ...$extra]));

    // Enlaces para alternar el modo acumulado conservando el nodo y los filtros.
    $sinModo = array_diff_key($filtrosQuery, ['acumulado' => null]);
    $enlaceModo = fn (bool $acc) => route('reportes.pendientes-dependencia',
        array_filter(['dependencia' => $foco?->codigo, ...$sinModo, 'acumulado' => $acc ? '1' : null]));
@endphp

<x-layouts.platform :areas="$areas" :area="$area">
    <x-page-header eyebrow="CATÁLOGO DE REPORTES" title="Pendientes por dependencia"
        description="Documentos no leídos y recibidos sin atender, en entrada y delegados, recorriendo la institución hasta el personal responsable." />

    {{-- Migas de pan del drill-down, con acceso al reporte general plano --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
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

    {{-- Filtros y modo de visualización --}}
    @php($hayFiltros = $filtros->prioridad || $filtros->estadoRecepcion || $filtros->bandeja)
    <form method="GET" action="{{ route('reportes.pendientes-dependencia') }}"
          class="mb-8 rounded-2xl border border-line bg-white p-4 sm:p-5">
        {{-- Conserva el nodo y el modo al aplicar filtros --}}
        @if ($foco)<input type="hidden" name="dependencia" value="{{ $foco->codigo }}">@endif
        @if ($acumulado)<input type="hidden" name="acumulado" value="1">@endif
        <div class="flex flex-wrap items-end gap-4">
            <div class="min-w-[9rem] flex-1">
                <label for="f-bandeja" class="mb-1.5 block text-[11px] font-semibold text-muted">Bandeja</label>
                <select id="f-bandeja" name="bandeja" class="field">
                    <option value="">Todas</option>
                    <option value="entrada" @selected($filtros->bandeja === 'entrada')>Entrada</option>
                    <option value="delegados" @selected($filtros->bandeja === 'delegados')>Delegados</option>
                </select>
            </div>
            <div class="min-w-[9rem] flex-1">
                <label for="f-estado" class="mb-1.5 block text-[11px] font-semibold text-muted">Estado de recepción</label>
                <select id="f-estado" name="estado" class="field">
                    <option value="">Todos</option>
                    <option value="0" @selected($filtros->estadoRecepcion === '0')>No leídos</option>
                    <option value="1" @selected($filtros->estadoRecepcion === '1')>Recibidos sin atender</option>
                </select>
            </div>
            <div class="min-w-[9rem] flex-1">
                <label for="f-prioridad" class="mb-1.5 block text-[11px] font-semibold text-muted">Prioridad</label>
                <select id="f-prioridad" name="prioridad" class="field">
                    <option value="">Todas</option>
                    <option value="1" @selected($filtros->prioridad === '1')>Normal</option>
                    <option value="2" @selected($filtros->prioridad === '2')>Urgente</option>
                    <option value="3" @selected($filtros->prioridad === '3')>Muy urgente</option>
                </select>
            </div>
            <div class="flex items-center gap-2">
                <x-button variant="primary">Aplicar</x-button>
                @if ($hayFiltros)
                    <a href="{{ $enlace($foco?->codigo, ['prioridad' => null, 'estado' => null, 'bandeja' => null]) }}"
                       class="btn btn-quiet">Limpiar</a>
                @endif
            </div>
        </div>
    </form>

    {{-- Panel del nodo en foco --}}
    <section aria-label="Resumen de {{ $tituloFoco }}" class="mb-10">
        <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="eyebrow text-emerald">EN FOCO</p>
                <h2 class="mt-2 text-xl font-medium">{{ $tituloFoco }}</h2>
                @unless ($esInstitucional)
                    <p class="mt-1 text-xs text-muted">
                        {{ $usarAcumulado ? 'Incluye las cifras de sus subdependencias.' : 'Solo las cifras propias de esta dependencia.' }}
                    </p>
                @endunless
            </div>
            <div class="text-right">
                <p class="text-xs text-muted">Total de asignaciones pendientes</p>
                <p class="text-3xl font-light tabular-nums text-ink">{{ number_format($totalFoco) }}</p>
            </div>
        </div>

        {{-- Alternar modo de cifras y abrir el detalle de la dependencia --}}
        @unless ($esInstitucional)
            <div class="mb-6 flex flex-wrap items-center gap-3">
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

        <div class="grid gap-5 md:grid-cols-2">
            <x-kpi-pendientes titulo="Entrada" descripcion="Dirigidos al encargado de la dependencia."
                :no-leidos="$entradaNoLeidos" :recibidos="$entradaRecibidos" />
            <x-kpi-pendientes titulo="Delegados" descripcion="Dirigidos a un empleado distinto del encargado."
                :no-leidos="$delegadosNoLeidos" :recibidos="$delegadosRecibidos" />
        </div>

        <div class="mt-5 rounded-2xl border border-line bg-white p-6">
            <h3 class="mb-4 text-sm font-semibold text-ink">Distribución por prioridad</h3>
            <x-barra-prioridad :normal="$pNormal" :urgente="$pUrgente" :muy-urgente="$pMuy" />
        </div>
    </section>

    {{-- Tabla navegable de dependencias hijas --}}
    @if (count($hijas) > 0)
        <section aria-label="Dependencias" class="rounded-2xl border border-line bg-white">
            <div class="flex items-center justify-between border-b border-line px-6 py-4">
                <h2 class="text-sm font-semibold text-ink">
                    {{ $esInstitucional ? 'Dependencias principales' : 'Dependencias dentro de ' . $foco->nombre }}
                </h2>
                <span class="status-label">{{ $usarAcumulado ? 'Acumulado con descendencia' : 'Cifras propias' }}</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] text-sm">
                    <thead>
                        <tr class="border-b border-line text-left text-[11px] uppercase tracking-wide text-muted">
                            <th scope="col" class="px-6 py-3 font-semibold">Dependencia</th>
                            <th scope="col" class="px-4 py-3 text-right font-semibold">Entrada</th>
                            <th scope="col" class="px-4 py-3 text-right font-semibold">Delegados</th>
                            <th scope="col" class="px-4 py-3 text-right font-semibold">Total</th>
                            <th scope="col" class="px-6 py-3 font-semibold">Magnitud</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($hijas as $h)
                            @php($tieneHijas = count($h->hijas) > 0)
                            <tr class="border-b border-line/70 last:border-0 hover:bg-paper/60">
                                <th scope="row" class="px-6 py-4 text-left font-normal">
                                    <a href="{{ $enlace($h->codigo) }}"
                                       class="group inline-flex items-center gap-2 font-medium text-ink hover:text-emerald">
                                        <span class="font-mono text-xs text-muted">{{ $h->codigo }}</span>
                                        <span>{{ $h->nombre }}</span>
                                        <span aria-hidden="true" class="text-emerald opacity-0 transition group-hover:opacity-100">↗</span>
                                    </a>
                                    @unless ($h->activa)
                                        <span class="ml-2 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] text-muted">inactiva</span>
                                    @endunless
                                    @if ($tieneHijas)
                                        <p class="mt-0.5 text-[11px] text-muted">{{ count($h->hijas) }} subdependencia(s)</p>
                                    @endif
                                </th>
                                <td class="px-4 py-4 text-right tabular-nums">{{ number_format($hEntrada($h)) }}</td>
                                <td class="px-4 py-4 text-right tabular-nums">{{ number_format($hDelegados($h)) }}</td>
                                <td class="px-4 py-4 text-right font-semibold tabular-nums">{{ number_format($hTotal($h)) }}</td>
                                <td class="px-6 py-4">
                                    <div class="h-2 w-full overflow-hidden rounded-full bg-paper" role="img"
                                         aria-label="{{ $hTotal($h) }} pendientes">
                                        <span class="block h-full rounded-full bg-emerald"
                                              style="width: {{ max(2, round($hTotal($h) / $maxHija * 100)) }}%"></span>
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
    <footer class="mt-10 flex flex-wrap gap-x-8 gap-y-2 border-t border-line pt-5 text-xs text-muted">
        <span><strong class="font-semibold text-ink">Publicación:</strong>
            {{ Carbon::parse($resumen->publicacion->fecha_publicacion)->translatedFormat('d \d\e F \d\e Y, H:i') }}</span>
        <span><strong class="font-semibold text-ink">Perfil:</strong> {{ ucfirst($resumen->publicacion->perfil) }}</span>
        <span><strong class="font-semibold text-ink">Versión de reglas:</strong> {{ $resumen->publicacion->version_reglas }}</span>
        <span><strong class="font-semibold text-ink">Unidad:</strong> asignaciones (destinos internos)</span>
        @if ($resumen->publicacion->nota)
            <span class="w-full text-[11px] italic">{{ $resumen->publicacion->nota }}</span>
        @endif
    </footer>
</x-layouts.platform>
