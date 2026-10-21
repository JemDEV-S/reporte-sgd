<x-layouts.administracion :title="$rol->exists ? 'Editar rol' : 'Crear rol'">
    <form method="POST" class="panel max-w-4xl space-y-6" action="{{ $rol->exists ? route('admin.roles.update', $rol) : route('admin.roles.store') }}">
        @csrf @if($rol->exists) @method('PUT') @endif
        @if($rol->exists)<p class="text-sm text-muted">Código: <code>{{ $rol->codigo }}</code></p>@else<x-form-field name="codigo" label="Código" hint="Letras minúsculas, números y guion bajo; comienza con una letra." required maxlength="80" />@endif
        <x-form-field name="nombre" label="Nombre del rol" :value="$rol->nombre" required maxlength="255" />
        @php($descripcion = old('descripcion', $rol->descripcion))
        <div><label for="descripcion" class="mb-2 block text-sm font-semibold">Descripción</label><textarea id="descripcion" class="field" name="descripcion" maxlength="1000" rows="3">{{ is_string($descripcion) ? $descripcion : '' }}</textarea></div>
        <p class="text-sm text-muted">Los cambios afectan a todas las cuentas con este rol. El acceso institucional, los datos personales y los asuntos se conceden de forma explícita.</p>
        @php($seleccionados = collect((array) old('permisos', session()->hasOldInput() ? [] : ($rol->exists ? $rol->permisos->modelKeys() : [])))->filter(fn ($valor) => is_scalar($valor))->map(fn ($valor) => (string) $valor)->all())
        <div class="grid gap-5 sm:grid-cols-2">
        @foreach($grupos as $grupo => $permisos)<fieldset class="rounded-xl border border-line p-4"><legend class="px-2 font-semibold">{{ ucfirst($grupo) }}</legend>
            @foreach($permisos as $permiso)<label class="mb-3 flex items-start gap-3"><input type="checkbox" name="permisos[]" value="{{ $permiso->id }}" @checked(in_array((string) $permiso->id, $seleccionados, true))><span><span class="block text-sm">{{ $permiso->descripcion }}</span><code class="text-xs text-muted">{{ $permiso->codigo }}</code></span></label>@endforeach
        </fieldset>@endforeach
        </div>
        <div class="flex flex-wrap gap-3"><x-button>Guardar rol</x-button><a class="btn btn-secondary" href="{{ route('admin.roles.index') }}">Volver</a></div>
    </form>
</x-layouts.administracion>
