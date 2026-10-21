<x-layouts.administracion title="Auditoría administrativa">
    <p class="mb-5 text-sm text-muted">Historial de cambios en cuentas y roles. Fechas en America/Lima.</p>
    <div class="panel overflow-x-auto"><table class="w-full text-left text-sm"><caption class="sr-only">Eventos administrativos</caption><thead><tr><th class="p-3">Fecha</th><th class="p-3">Responsable</th><th class="p-3">Acción</th><th class="p-3">Registro</th><th class="p-3">Cambios</th></tr></thead><tbody>
    @forelse($eventos as $evento)<tr class="border-t border-line"><td class="p-3">{{ \Carbon\CarbonImmutable::parse($evento->ocurrido_en)->timezone('America/Lima')->format('d/m/Y H:i:s') }}</td><td class="p-3">{{ trim($evento->nombre.' '.$evento->apellidos) ?: 'Sistema' }}</td><td class="p-3">{{ $evento->accion }}</td><td class="p-3">{{ $evento->entidad_tipo }} #{{ $evento->entidad_clave }}</td><td class="p-3"><details><summary>Ver cambios</summary><pre class="mt-2 whitespace-pre-wrap text-xs">{{ json_encode(json_decode($evento->contexto), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></details></td></tr>
    @empty<tr><td class="p-6 text-muted" colspan="5">Todavía no hay cambios administrativos registrados.</td></tr>@endforelse
    </tbody></table><div class="mt-5">{{ $eventos->links() }}</div></div>
</x-layouts.administracion>
