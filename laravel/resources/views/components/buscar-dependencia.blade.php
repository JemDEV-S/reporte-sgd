@props(['busqueda' => null, 'resultados' => [], 'mostrarResultados' => false, 'publicacion', 'rutas' => [], 'filtros' => null])
<section class="mb-4 rounded-xl border border-line bg-white p-3" aria-label="Búsqueda y filtros">
    <form method="GET" action="{{ url()->current() }}" class="report-toolbar">
        @foreach (request()->query() as $clave => $valor)
            @if (is_scalar($valor) && ! in_array($clave, ['publicacion', 'buscar', 'page', 'bandeja', 'estado', 'prioridad'], true))<input type="hidden" name="{{ $clave }}" value="{{ $valor }}">@endif
        @endforeach
        <input type="hidden" name="publicacion" value="{{ $publicacion }}">
        <div class="relative col-span-2 min-w-0 xl:col-span-1">
            <label for="buscar-dependencia" class="sr-only">Buscar dependencia por nombre o código</label>
            <x-icon name="explorador" class="pointer-events-none absolute top-3 left-3 size-4 text-muted" />
            <input id="buscar-dependencia" class="field pl-9" name="buscar" type="search" value="{{ $busqueda }}" maxlength="100" placeholder="Buscar dependencia…">
            @error('buscar')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
        </div>
        @if ($filtros)
            @foreach ([
                'bandeja' => ['Bandeja', $filtros->bandeja, ['' => 'Todas las bandejas', 'entrada' => 'Entrada', 'delegados' => 'Delegados']],
                'estado' => ['Estado', $filtros->estadoRecepcion, ['' => 'Todos los estados', '0' => 'No leídos', '1' => 'Recibidos sin atender']],
                'prioridad' => ['Prioridad', $filtros->prioridad, ['' => 'Todas las prioridades', '1' => 'Normal', '2' => 'Urgente', '3' => 'Muy urgente']],
            ] as $nombre => [$etiqueta, $actual, $opciones])
                <div class="min-w-0">
                    <label for="f-{{ $nombre }}" class="sr-only">{{ $etiqueta }}</label>
                    <select id="f-{{ $nombre }}" name="{{ $nombre }}" class="field">
                        @foreach ($opciones as $valor => $texto)<option value="{{ $valor }}" @selected((string) $actual === (string) $valor)>{{ $texto }}</option>@endforeach
                    </select>
                </div>
            @endforeach
        @endif
        <div class="flex items-center gap-2">
            <button type="submit" class="btn btn-primary">Aplicar</button>
            @if ($busqueda !== null || ($filtros && count($filtros->descripcion())))
                <a class="btn btn-quiet" href="{{ request()->fullUrlWithoutQuery(['buscar', 'bandeja', 'estado', 'prioridad', 'page']) }}">Limpiar</a>
            @endif
        </div>
    </form>
    @if ($mostrarResultados && $busqueda !== null)
        <div class="mt-4 border-t border-line pt-4">
            <p class="mb-3 text-xs font-medium text-muted">{{ count($resultados) }} resultado(s) para «{{ $busqueda }}»</p>
            <div class="grid max-h-80 gap-2 overflow-y-auto sm:grid-cols-2 xl:grid-cols-3">
                @forelse ($resultados as $resultado)
                    <a href="{{ route('reportes.pendientes-dependencia', [...request()->except(['buscar', 'page']), 'publicacion' => $publicacion, 'dependencia' => $resultado->codigo]) }}" class="flex items-center justify-between gap-3 rounded-xl border border-line p-3 text-sm hover:border-emerald/40 hover:bg-emerald/5">
                        <span><span class="mb-1 block font-mono text-xs text-muted">{{ $resultado->codigo }}</span><span class="font-medium">{{ $resultado->nombre }}</span><span class="mt-1 block text-xs text-muted">{{ $rutas[$resultado->codigo] ?? '' }}</span></span><x-icon name="arrow" class="size-4 text-emerald" />
                    </a>
                @empty
                    <p class="py-3 text-sm text-muted">No se encontraron dependencias. Prueba con otro nombre o código.</p>
                @endforelse
            </div>
        </div>
    @endif
</section>
