@php
    use App\Reportes\PendientesDependencia\IntegrantePadron;

    /**
     * Fragmento del padrón de integrantes de una dependencia (§5). Lo carga el
     * modal bajo demanda y también sirve como respuesta de fallback GET sin JS.
     * Recibe:
     *   $padron    PadronIntegrantes
     *   $contexto  ContextoReporte
     *   $busqueda  ?string (texto de búsqueda aplicado al padrón)
     *
     * Marcado para el JS del modal:
     *   [data-padron-fragmento]  raíz del fragmento
     *   [data-persona-link]      enlace que carga el detalle de una persona
     *   [data-padron-busqueda]   formulario de búsqueda (interceptable)
     */
    $filtrosQuery = array_filter([
        'prioridad' => $padron->filtros->prioridad,
        'estado' => $padron->filtros->estadoRecepcion,
        'bandeja' => $padron->filtros->bandeja,
        'publicacion' => $padron->publicacion->id,
    ], fn ($v) => $v !== null);

    $rutaPersona = fn (IntegrantePadron $i, array $extra = []) => route(
        'reportes.pendientes-dependencia.integrantes.persona',
        ['dependencia' => $padron->codigo, 'empleado' => $i->codigo ?? '00000', ...$filtrosQuery, ...$extra],
    );
@endphp

<div data-padron-fragmento
     data-padron-url="{{ route('reportes.pendientes-dependencia.integrantes', ['dependencia' => $padron->codigo, ...$filtrosQuery]) }}">
    {{-- Encabezado del padrón y conciliación (§5.3) --}}
    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="text-xs text-muted">{{ $padron->codigo }} · Padrón activo</p>
            <p class="text-sm text-ink">
                {{ number_format($padron->totalPersonas()) }} integrante(s) activo(s) ·
                {{ number_format($padron->totalIntegrantes) }} pendiente(s) del padrón
                @if ($padron->tieneOtrasAsignaciones())
                    · {{ number_format($padron->totalOtras) }} en otras asignaciones
                @endif
                · {{ number_format($padron->totalDetalle) }} en total
            </p>
            @unless ($padron->concilia())
                <p class="mt-1 text-xs font-semibold text-red-600">Incidencia: la suma del padrón y otras asignaciones no iguala el total del detalle.</p>
            @endunless
        </div>

        {{-- Búsqueda del padrón (GET; el JS la intercepta si está disponible) --}}
        <form method="get" action="{{ route('reportes.pendientes-dependencia.integrantes', ['dependencia' => $padron->codigo]) }}"
              data-padron-busqueda class="flex items-center gap-2">
            @foreach ($filtrosQuery as $clave => $valor)
                <input type="hidden" name="{{ $clave }}" value="{{ $valor }}">
            @endforeach
            <label for="padron-q" class="sr-only">Buscar integrante</label>
            <input id="padron-q" type="search" name="q" value="{{ $busqueda }}" placeholder="Buscar por nombre o código"
                   class="w-56 rounded-lg border border-line px-3 py-1.5 text-sm" />
            <button type="submit" class="rounded-lg border border-emerald/40 bg-emerald/5 px-3 py-1.5 text-sm font-semibold text-emerald hover:bg-emerald/10">Buscar</button>
        </form>
    </div>

    {{-- Tabla del padrón: incluye activos con saldo cero --}}
    <div class="overflow-x-auto rounded-xl border border-line">
        <table class="w-full min-w-[640px] text-sm">
            <caption class="sr-only">Integrantes activos de la dependencia {{ $padron->codigo }}</caption>
            <thead>
                <tr class="border-b border-line bg-paper/60 text-left text-xs uppercase tracking-wide text-muted">
                    <th scope="col" class="px-4 py-3 font-semibold">Integrante</th>
                    <th scope="col" class="px-4 py-3 text-right font-semibold">Total</th>
                    <th scope="col" class="px-4 py-3 text-right font-semibold">No leídos</th>
                    <th scope="col" class="px-4 py-3 text-right font-semibold">Recibidos</th>
                    <th scope="col" class="px-4 py-3 text-right font-semibold">Urgentes</th>
                    <th scope="col" class="px-4 py-3 text-right font-semibold">Pendientes</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($padron->integrantes as $integrante)
                    {{-- @var IntegrantePadron $integrante --}}
                    <tr class="border-b border-line/70 last:border-0 hover:bg-paper/60">
                        <td class="px-4 py-3">
                            <span class="font-medium text-ink">{{ $integrante->nombre ?? 'Sin nombre registrado' }}</span>
                            @if ($integrante->esEncargado)
                                <span class="ml-1 rounded-full bg-emerald/10 px-2 py-0.5 text-xs font-semibold text-emerald">Encargado</span>
                            @endif
                            <p class="mt-0.5 flex flex-wrap items-center gap-1 font-mono text-xs text-muted">
                                <span>{{ $integrante->codigo }}</span>
                                @foreach ($integrante->vias as $via)
                                    <span class="rounded bg-slate-100 px-1 py-0.5 text-xs font-normal not-italic text-muted">{{ $via }}</span>
                                @endforeach
                            </p>
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums font-semibold text-ink">{{ number_format($integrante->total) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-muted">{{ number_format($integrante->noLeidos) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-muted">{{ number_format($integrante->recibidos) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums {{ $integrante->urgentes > 0 ? 'text-red-600' : 'text-muted' }}">{{ number_format($integrante->urgentes) }}</td>
                        <td class="px-4 py-3 text-right">
                            @if ($integrante->total > 0)
                                <a href="{{ $rutaPersona($integrante) }}" data-persona-link
                                   class="text-sm font-semibold text-emerald hover:underline">Ver pendientes</a>
                            @else
                                <span class="text-xs text-muted">Sin pendientes</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-6 py-8 text-center text-sm text-muted">
                        @if ($busqueda !== null)
                            Ningún integrante coincide con «{{ $busqueda }}».
                        @else
                            Esta oficina no tiene integrantes activos registrados en el padrón de esta publicación.
                        @endif
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($padron->integrantes->hasPages())
        <div class="mt-4">{{ $padron->integrantes->links() }}</div>
    @endif

    {{-- Otras asignaciones: destinatarios fuera del padrón activo (§5.3) --}}
    @if ($padron->tieneOtrasAsignaciones())
        <div class="mt-8">
            <h3 class="text-sm font-semibold text-ink">Otras asignaciones pendientes</h3>
            <p class="mb-3 text-xs text-muted">Destinatarios inactivos, sin pertenencia vigente a esta oficina o sin registro de empleado. Se muestran para explicar el total.</p>
            <div class="overflow-x-auto rounded-xl border border-line">
                <table class="w-full min-w-[640px] text-sm">
                    <caption class="sr-only">Otras asignaciones pendientes de la dependencia {{ $padron->codigo }}</caption>
                    <thead>
                        <tr class="border-b border-line bg-paper/60 text-left text-xs uppercase tracking-wide text-muted">
                            <th scope="col" class="px-4 py-3 font-semibold">Destinatario</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Motivo</th>
                            <th scope="col" class="px-4 py-3 text-right font-semibold">Total</th>
                            <th scope="col" class="px-4 py-3 text-right font-semibold">Urgentes</th>
                            <th scope="col" class="px-4 py-3 text-right font-semibold">Pendientes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($padron->otrasAsignaciones as $otra)
                            {{-- @var IntegrantePadron $otra --}}
                            <tr class="border-b border-line/70 last:border-0 hover:bg-paper/60">
                                <td class="px-4 py-3">
                                    @if ($otra->esSinDestinatario)
                                        <span class="italic text-muted">Sin destinatario identificado</span>
                                    @else
                                        <span class="text-ink">{{ $otra->nombre ?? 'Sin nombre registrado' }}</span>
                                        <p class="mt-0.5 font-mono text-xs text-muted">{{ $otra->codigo }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-xs text-muted">{{ $otra->motivo() }}</td>
                                <td class="px-4 py-3 text-right tabular-nums font-semibold text-ink">{{ number_format($otra->total) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums {{ $otra->urgentes > 0 ? 'text-red-600' : 'text-muted' }}">{{ number_format($otra->urgentes) }}</td>
                                <td class="px-4 py-3 text-right">
                                    @if ($otra->esSinDestinatario)
                                        <a href="{{ $rutaPersona($otra, ['grupo' => 'sin_destinatario']) }}" data-persona-link
                                           class="text-sm font-semibold text-emerald hover:underline">Ver pendientes</a>
                                    @else
                                        <a href="{{ $rutaPersona($otra) }}" data-persona-link
                                           class="text-sm font-semibold text-emerald hover:underline">Ver pendientes</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
