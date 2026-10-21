<x-layouts.administracion :title="$usuario->exists ? 'Editar usuario' : 'Crear usuario'">
    <form class="panel max-w-4xl space-y-6" method="POST" action="{{ $usuario->exists ? route('admin.usuarios.update', $usuario) : route('admin.usuarios.store') }}">
        @csrf @if($usuario->exists) @method('PUT') @endif
        <div class="grid gap-5 sm:grid-cols-2">
            <x-form-field name="dni" label="DNI" :value="$usuario->dni" required pattern="[0-9]{8}" maxlength="8" inputmode="numeric" />
            <x-form-field name="email" label="Correo electrónico" type="email" :value="$usuario->email" required maxlength="255" />
            <x-form-field name="nombre" label="Nombres" :value="$usuario->nombre" required maxlength="255" />
            <x-form-field name="apellidos" label="Apellidos" :value="$usuario->apellidos" required maxlength="255" />
            <div><label class="mb-2 block text-sm font-semibold" for="activo">Estado de la cuenta</label><select class="field" id="activo" name="activo"><option value="1" @selected(in_array(old('activo', (int) $usuario->activo), [1, '1', true], true))>Activo</option><option value="0" @selected(in_array(old('activo', (int) $usuario->activo), [0, '0', false], true))>Inactivo</option></select></div>
        </div>
        <fieldset><legend class="mb-3 font-semibold">Roles de acceso</legend>
            <p class="mb-3 text-sm text-muted">Los permisos efectivos son la suma de los roles asignados. La consulta de oficinas requiere además un alcance de datos autorizado.</p>
            @php($seleccionados = collect((array) old('roles', session()->hasOldInput() ? [] : ($usuario->exists ? $usuario->roles->modelKeys() : [])))->filter(fn ($valor) => is_scalar($valor))->map(fn ($valor) => (string) $valor)->all())
            @foreach($roles as $rol)
                <label class="mb-2 flex items-start gap-3 rounded-xl border border-line p-3">
                    <input type="checkbox" name="roles[]" value="{{ $rol->id }}" @checked(in_array((string) $rol->id, $seleccionados, true)) @disabled(! auth()->user()->can('roles.gestionar'))>
                    <span><span class="block text-sm font-semibold">{{ $rol->nombre }}</span><span class="text-xs text-muted">{{ $rol->descripcion }}</span></span>
                </label>
                @cannot('roles.gestionar') @if(in_array((string) $rol->id, $seleccionados, true))<input type="hidden" name="roles[]" value="{{ $rol->id }}">@endif @endcannot
            @endforeach
            @cannot('roles.gestionar')<p class="text-sm text-muted">La asignación de roles requiere el permiso de gestión de roles.</p>@endcannot
        </fieldset>
        <fieldset><legend class="mb-3 font-semibold">{{ $usuario->exists ? 'Restablecer contraseña' : 'Contraseña inicial' }}</legend>
            <p class="mb-3 text-sm text-muted">Mínimo 12 caracteres, con letras y números. {{ $usuario->exists ? 'Deja ambos campos vacíos para conservar la contraseña.' : '' }}</p>
            <div class="grid gap-5 sm:grid-cols-2"><x-form-field name="password" label="Contraseña" type="password" autocomplete="new-password" maxlength="72" :required="! $usuario->exists" /><x-form-field name="password_confirmation" label="Confirmar contraseña" type="password" autocomplete="new-password" maxlength="72" :required="! $usuario->exists" /></div>
        </fieldset>
        <div class="flex flex-wrap gap-3"><x-button>Guardar usuario</x-button><a class="btn btn-secondary" href="{{ route('admin.usuarios.index') }}">Volver</a></div>
    </form>
</x-layouts.administracion>
