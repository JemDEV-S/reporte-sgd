@props(['areas', 'area'])
<nav aria-label="Navegación principal" class="space-y-2">
<a href="{{ route('home') }}" 
@if ($area === null && request()->routeIs('home')) aria-current="page" @endif
 class="nav-link">
<x-icon name="home" />Inicio</a>
<p class="px-4 pt-7 pb-2 text-xs font-semibold tracking-[.18em] text-slate-400">ÁREAS DE TRABAJO</p>
@foreach ($areas as $key => $item)
@if ($item['allowed'])<a href="{{ route('platform.area', $key) }}" 
@if ($area === $key) aria-current="page" @endif
 class="nav-link">
<x-icon :name="$key === 'analisis' ? 'chart' : $key" />{{ $item['name'] }}</a>@endif

@endforeach

@if (! $areas->contains('allowed', true))<p class="px-4 text-xs leading-6 text-slate-400">Tu cuenta no tiene áreas documentales asignadas.</p>@endif
@canany(['usuarios.gestionar', 'roles.gestionar', 'fuentes.gestionar'])
<p class="px-4 pt-7 pb-2 text-xs font-semibold tracking-[.18em] text-slate-400">ADMINISTRACIÓN</p>
@can('usuarios.gestionar')
<a href="{{ route('admin.usuarios.index') }}" class="nav-link" @if(request()->routeIs('admin.usuarios.*')) aria-current="page" @endif><x-icon name="shield" />Usuarios</a>
@endcan
@can('roles.gestionar')
<a href="{{ route('admin.roles.index') }}" class="nav-link" @if(request()->routeIs('admin.roles.*')) aria-current="page" @endif><x-icon name="shield" />Roles y permisos</a>
<a href="{{ route('admin.auditoria') }}" class="nav-link" @if(request()->routeIs('admin.auditoria')) aria-current="page" @endif><x-icon name="shield" />Auditoría</a>
@endcan
@can('fuentes.gestionar')
<a href="{{ route('admin.sincronizacion.index') }}" class="nav-link" @if(request()->routeIs('admin.sincronizacion.*')) aria-current="page" @endif><x-icon name="shield" />Sincronización SGD</a>
@endcan
@endcanany
</nav>
