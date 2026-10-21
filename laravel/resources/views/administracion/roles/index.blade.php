<x-layouts.administracion title="Roles y permisos">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3"><p class="text-sm text-muted">Define qué capacidades concede cada rol.</p><a class="btn btn-primary" href="{{ route('admin.roles.create') }}">Crear rol</a></div>
    <div class="panel overflow-x-auto"><table class="w-full text-left text-sm"><caption class="sr-only">Roles registrados</caption><thead><tr><th class="p-3">Rol</th><th class="p-3">Usuarios</th><th class="p-3">Permisos</th><th class="p-3">Acción</th></tr></thead><tbody>
        @foreach($roles as $rol)<tr class="border-t border-line"><td class="p-3"><p class="font-semibold">{{ $rol->nombre }}</p><p class="text-muted">{{ $rol->descripcion }}</p><code class="text-xs">{{ $rol->codigo }}</code></td><td class="p-3">{{ $rol->usuarios_count }}</td><td class="p-3">{{ $rol->permisos_count }}</td><td class="p-3"><a class="btn btn-secondary" href="{{ route('admin.roles.edit', $rol) }}">Editar</a></td></tr>@endforeach
    </tbody></table><div class="mt-5">{{ $roles->links() }}</div></div>
</x-layouts.administracion>
