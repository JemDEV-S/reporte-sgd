@php
    use App\Reportes\PendientesDependencia\FilaDetalle;

    /**
     * Tabla de asignaciones pendientes, compartida por el detalle y (en una fase
     * posterior) el modal de integrantes. Recibe:
     *   $filas      LengthAwarePaginator<FilaDetalle>
     *   $corte      Carbon (fecha de corte, para encabezado de antigüedad)
     * No consulta SQL: solo presenta FilaDetalle ya proyectada según permisos.
     */
    $prioridadColor = ['1' => 'bg-blue-600', '2' => 'bg-orange-500', '3' => 'bg-red-600'];
@endphp

<div class="overflow-x-auto">
    <table class="w-full min-w-[1040px] text-sm">
        <caption class="sr-only">Asignaciones pendientes</caption>
        <thead>
            <tr class="border-b border-line text-left text-xs uppercase tracking-wide text-muted">
                <th scope="col" class="px-4 py-3 font-semibold">Tipo</th>
                <th scope="col" class="px-4 py-3 font-semibold">N.º de documento</th>
                <th scope="col" class="px-4 py-3 font-semibold">Emisión</th>
                <th scope="col" class="px-4 py-3 font-semibold">Asunto</th>
                <th scope="col" class="px-4 py-3 font-semibold">Origen / remitente</th>
                <th scope="col" class="px-4 py-3 font-semibold">Destinatario</th>
                <th scope="col" class="px-4 py-3 font-semibold">Bandeja</th>
                <th scope="col" class="px-4 py-3 font-semibold">Estado</th>
                <th scope="col" class="px-4 py-3 font-semibold">Prioridad</th>
                <th scope="col" class="px-4 py-3 text-right font-semibold">Antigüedad</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($filas as $fila)
                {{-- @var FilaDetalle $fila --}}
                <tr class="border-b border-line/70 align-top last:border-0 hover:bg-paper/60">
                    {{-- Tipo --}}
                    <td class="px-4 py-4">
                        @if ($fila->tipoDescripcion !== null)
                            <span class="text-ink">{{ $fila->tipoDescripcion }}</span>
                        @else
                            <span class="text-muted">Tipo sin descripción</span>
                        @endif
                        @if ($fila->tipoCodigo !== null)
                            <p class="mt-0.5 font-mono text-xs text-muted">{{ $fila->tipoCodigo }}</p>
                        @endif
                    </td>

                    {{-- Número documental + clave técnica desplegable --}}
                    <td class="px-4 py-4">
                        @if ($fila->numeroDocumento !== null)
                            <span class="font-medium text-ink">{{ $fila->numeroDocumento }}</span>
                            @if ($fila->numeroIncompleto)
                                <span class="ml-1 rounded bg-amber-100 px-1 py-0.5 text-xs font-semibold text-amber-700"
                                      title="Faltan componentes del número en la fuente">incompleto</span>
                            @endif
                        @else
                            <span class="text-muted">Sin número registrado</span>
                        @endif
                        <details class="mt-1">
                            <summary class="cursor-pointer text-xs text-muted hover:text-ink">Clave técnica</summary>
                            <p class="mt-0.5 font-mono text-xs text-muted">{{ $fila->claveTecnica() }}</p>
                            @if ($fila->fechaRecepcion !== null)
                                <p class="font-mono text-xs text-muted">Recepción: {{ $fila->fechaRecepcion->format('d/m/Y H:i') }}</p>
                            @endif
                        </details>
                    </td>

                    {{-- Fecha de emisión --}}
                    <td class="px-4 py-4 tabular-nums">
                        @if ($fila->fechaEmision !== null)
                            <span class="text-ink">{{ $fila->fechaEmision->format('d/m/Y') }}</span>
                        @else
                            <span class="text-muted">Sin fecha de emisión</span>
                        @endif
                    </td>

                    {{-- Asunto --}}
                    <td class="px-4 py-4">
                        <div class="max-w-[22rem]">
                            @if ($fila->asuntoRestringido)
                                <span class="italic text-muted">Restringido</span>
                            @elseif ($fila->asunto !== null)
                                <details>
                                    <summary class="line-clamp-2 cursor-pointer text-ink marker:text-muted">{{ $fila->asunto }}</summary>
                                    <p class="mt-1 whitespace-pre-line text-ink">{{ $fila->asunto }}</p>
                                </details>
                            @else
                                <span class="text-muted">Sin asunto registrado</span>
                            @endif
                        </div>
                    </td>

                    {{-- Origen / remitente --}}
                    <td class="px-4 py-4">
                        <div class="max-w-[16rem]">
                            @if ($fila->origenRestringido)
                                <span class="italic text-muted">Restringido</span>
                            @elseif ($fila->origen !== null)
                                <span class="text-ink">{{ $fila->origen }}</span>
                            @else
                                <span class="text-muted">Origen no identificado</span>
                            @endif
                        </div>
                    </td>

                    {{-- Destinatario --}}
                    <td class="px-4 py-4">
                        @if ($fila->destinatarioRestringido)
                            <span class="italic text-muted">Restringido</span>
                        @elseif ($fila->destinatarioNombre !== null)
                            <span class="text-ink">{{ $fila->destinatarioNombre }}</span>
                            @if ($fila->destinatarioCodigo !== null)
                                <p class="mt-0.5 font-mono text-xs text-muted">{{ $fila->destinatarioCodigo }}</p>
                            @endif
                        @else
                            <span class="text-muted">Destinatario sin nombre registrado</span>
                        @endif
                    </td>

                    {{-- Bandeja --}}
                    <td class="px-4 py-4">
                        <span class="rounded-full px-2 py-0.5 text-xs {{ $fila->esEntrada ? 'bg-emerald/10 text-emerald' : 'bg-slate-100 text-muted' }}">
                            {{ $fila->bandeja() }}
                        </span>
                    </td>

                    {{-- Estado --}}
                    <td class="px-4 py-4 text-xs">{{ $fila->estadoNombre() }}</td>

                    {{-- Prioridad --}}
                    <td class="px-4 py-4">
                        <span class="inline-flex items-center gap-1.5 text-xs">
                            <span class="inline-block h-2.5 w-2.5 rounded-sm {{ $prioridadColor[$fila->prioridadCodigo] ?? 'bg-slate-300' }}" aria-hidden="true"></span>
                            {{ $fila->prioridadNombre() ?? 'Sin prioridad identificada' }}
                        </span>
                    </td>

                    {{-- Antigüedad --}}
                    <td class="px-4 py-4 text-right tabular-nums">
                        @if ($fila->dias === null)
                            <span class="text-xs text-muted">Sin fecha</span>
                        @else
                            <span class="{{ $fila->dias < 0 ? 'text-red-600' : 'text-ink' }}">{{ $fila->dias }} d</span>
                            @if ($fila->antiguedadPorEmision)
                                <p class="text-xs text-muted" title="No hay recepción; se usa la fecha de emisión">desde emisión</p>
                            @endif
                            @if ($fila->dias < 0)
                                <p class="text-xs text-red-600">fecha futura</p>
                            @endif
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="10" class="px-6 py-10 text-center text-sm text-muted">No hay asignaciones pendientes con los filtros aplicados.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
