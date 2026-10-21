<x-layouts.administracion title="Sincronización SGD">
    <div data-sync-monitor data-sync-status-url="{{ route('admin.sincronizacion.estado', request()->only('page')) }}" data-sync-active="{{ $activa ? '1' : '0' }}">
    <p class="mb-5 text-sm text-muted">Administra la actualización de los datos documentales. Fechas y horarios en America/Lima.</p>
    <div data-sync-fragment="resumen">@include('administracion.sincronizacion.resumen')</div>
    <div class="mb-6 grid gap-5 lg:grid-cols-2">
        <form method="POST" action="{{ route('admin.sincronizacion.update') }}" class="panel space-y-4">
            @csrf @method('PUT')
            <h2 class="font-semibold">Programación automática</h2>
            <div><label for="automatica" class="mb-2 block text-sm font-semibold">Estado</label><select name="automatica" id="automatica" class="field"><option value="1" @selected(in_array(old('automatica', $configuracion->automatica), [true, 1, '1'], true))>Activada</option><option value="0" @selected(in_array(old('automatica', $configuracion->automatica), [false, 0, '0'], true))>Pausada</option></select></div>
            <div><label for="intervalo_minutos" class="mb-2 block text-sm font-semibold">Frecuencia</label><select class="field" id="intervalo_minutos" name="intervalo_minutos">@foreach([5, 10, 15, 30, 60] as $minutos)<option value="{{ $minutos }}" @selected(old('intervalo_minutos', $configuracion->intervalo_minutos) == $minutos)>Cada {{ $minutos }} minutos</option>@endforeach</select></div>
            <p class="text-xs text-muted">Un cambio de frecuencia inicia un nuevo intervalo desde este momento. Pausar no cancela solicitudes pendientes ni una ingesta en curso.</p>
            <x-button>Guardar programación</x-button>
        </form>
        <div data-sync-fragment="accion">@include('administracion.sincronizacion.accion')</div>
    </div>
    <div data-sync-fragment="historial">@include('administracion.sincronizacion.historial')</div>
    <p class="mt-3 text-xs text-muted" data-sync-feedback role="status" aria-live="polite">El estado se actualiza automáticamente. También puedes usar Actualizar estado.</p>
    </div>
</x-layouts.administracion>
