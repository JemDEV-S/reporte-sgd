@props(['areas', 'area'])
<nav aria-label="Navegación principal" class="space-y-2">
<a href="{{ route('home') }}" 
@if ($area === null) aria-current="page" @endif
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
</nav>
