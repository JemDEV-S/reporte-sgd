        <section class="panel"><h2 class="font-semibold">Actualizar ahora</h2><p class="my-4 text-sm text-muted">Solicita una sincronización incremental de los cambios del SGD. Puedes continuar utilizando el sistema mientras se procesa. Esta acción también está disponible con la programación pausada.</p>
            <form method="POST" action="{{ route('admin.sincronizacion.ejecutar') }}">@csrf<x-button id="sync-manual-button" :disabled="$activa !== null">{{ $textoBoton }}</x-button></form>
            <a class="btn btn-secondary mt-4" href="{{ route('admin.sincronizacion.index') }}">Actualizar estado</a>
        </section>
