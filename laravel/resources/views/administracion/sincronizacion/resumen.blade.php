    <div class="mb-5 grid gap-4 sm:grid-cols-3">
        <section class="panel"><h2 class="text-sm font-semibold">Datos disponibles</h2><p class="mt-3 text-xl font-semibold">{{ $publicacion ? $publicacion->fecha_publicacion->timezone('America/Lima')->format('d/m/Y H:i') : 'Sin publicación' }}</p><p class="mt-2 text-xs text-muted">{{ $publicacion ? $publicacion->fecha_publicacion->diffForHumans() : 'Se necesita una carga completa inicial.' }}</p>@if($ultimaExitosa)<p class="mt-2 text-xs text-muted">Última sincronización exitosa: {{ $ultimaExitosa->fin_en->timezone('America/Lima')->format('d/m/Y H:i:s') }}</p>@endif</section>
        <section class="panel"><h2 class="text-sm font-semibold">Próxima ejecución prevista</h2><p class="mt-3 text-xl font-semibold">{{ $configuracion->automatica ? ($configuracion->proxima_ejecucion_en?->timezone('America/Lima')->format('d/m/Y H:i') ?? 'En la próxima comprobación') : 'Programación pausada' }}</p><p class="mt-2 text-xs text-muted">Depende de la disponibilidad del servicio y de que no haya otra ingesta en curso.</p></section>
        <section class="panel"><h2 class="text-sm font-semibold">Estado del servicio</h2>
            <p class="mt-3 text-sm">Programador: {{ $schedulerReciente ? 'Actividad reciente' : 'Sin actividad reciente' }}</p>
            <p class="mt-2 text-sm">Procesador: {{ $activa?->estado === 'ejecutando' ? 'Sincronización en curso' : ($workerReciente ? 'Disponible' : 'Sin actividad reciente') }}</p>
            <p class="mt-2 text-xs text-muted">@if($activa)Solicitud #{{ $activa->id }}: {{ $activa->estado === 'pendiente' ? 'esperando procesamiento' : 'en curso' }}.@else No hay solicitudes pendientes.@endif</p>
        </section>
    </div>
    @if(! $workerReciente && $activa?->estado !== 'ejecutando')
        <x-alert variant="error" class="mb-5">El procesador no registra actividad reciente. @if($activa)La solicitud #{{ $activa->id }} está guardada y espera procesamiento; todavía no se está sincronizando.@else Las solicitudes quedarán pendientes hasta que el servicio esté disponible.@endif Contacta con el responsable del servicio para revisar su inicio.</x-alert>
    @endif
