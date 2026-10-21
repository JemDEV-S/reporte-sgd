<x-layouts.administracion title="Usuarios">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-muted">Administra cuentas y asigna sus roles de acceso.</p>
        <a class="btn btn-primary" href="{{ route('admin.usuarios.create') }}">Crear usuario</a>
    </div>
    <form method="GET" class="panel mb-5 flex flex-wrap items-end gap-3">
        <div class="min-w-56 flex-1"><label for="buscar" class="mb-2 block text-sm font-semibold">Buscar usuario</label><input class="field" id="buscar" name="buscar" value="{{ $buscar }}" maxlength="100" placeholder="DNI, nombre o correo"></div>
        <div><label for="estado" class="mb-2 block text-sm font-semibold">Estado</label><select class="field" name="estado" id="estado"><option value="">Todos</option><option value="activos" @selected(request('estado') === 'activos')>Activos</option><option value="inactivos" @selected(request('estado') === 'inactivos')>Inactivos</option></select></div>
        <x-button>Buscar</x-button><a class="btn btn-secondary" href="{{ route('admin.usuarios.index') }}">Limpiar</a>
    </form>
    <div class="panel overflow-x-auto">
        <table class="w-full text-left text-sm"><caption class="sr-only">Cuentas registradas</caption><thead><tr><th class="p-3">Usuario</th><th class="p-3">DNI</th><th class="p-3">Roles</th><th class="p-3">Estado</th><th class="p-3">Acción</th></tr></thead>
        <tbody>@forelse($usuarios as $usuario)<tr class="border-t border-line"><td class="p-3"><p class="font-semibold">{{ $usuario->nombre_completo }}</p><p class="text-muted">{{ $usuario->email }}</p></td><td class="p-3">{{ $usuario->dni }}</td><td class="p-3">{{ $usuario->roles->pluck('nombre')->join(', ') ?: 'Sin roles' }}</td><td class="p-3">{{ $usuario->activo ? 'Activo' : 'Inactivo' }}</td><td class="p-3"><a class="btn btn-secondary" aria-label="Editar a {{ $usuario->nombre_completo }}" href="{{ route('admin.usuarios.edit', $usuario) }}">Editar</a></td></tr>
        @empty<tr><td class="p-6 text-muted" colspan="5">No se encontraron usuarios.</td></tr>@endforelse</tbody></table>
        <div class="mt-5">{{ $usuarios->links() }}</div>
    </div>
</x-layouts.administracion>
