@props(['areas', 'area'])
<x-layouts.base :title="$area ? $areas[$area]['name'] : 'Inicio'">
<div class="min-h-screen lg:grid lg:grid-cols-[264px_1fr]">
<aside class="flex flex-col bg-ink px-5 py-6 text-white lg:min-h-screen lg:p-6">
<x-brand class="mb-8 px-2" />
<div class="hidden lg:block">
<x-navigation :areas="$areas" :area="$area" />
</div>
<details class="lg:hidden">
<summary class="cursor-pointer rounded-lg py-2 text-sm">Menú de navegación</summary>
<div class="pt-4">
<x-navigation :areas="$areas" :area="$area" />
</div>
</details>
<div class="mt-auto hidden border-t border-white/10 px-2 pt-6 text-xs leading-6 text-slate-400 lg:block">Municipalidad Distrital<br>de San Jerónimo</div>
</aside>
<div class="min-w-0">
<header class="flex flex-wrap items-center justify-between gap-3 border-b border-line bg-white px-6 py-5 lg:px-12">
<p class="text-xs text-muted">SIRMD <span class="mx-3 text-slate-300">/</span> {{ $area ? $areas[$area]['name'] : 'Inicio' }}</p>
<div class="flex items-center gap-5">
<span class="max-w-48 truncate text-sm font-medium">{{ auth()->user()->nombre_completo }}</span>
<form method="POST" action="{{ route('logout') }}">@csrf<x-button variant="quiet">Cerrar sesión</x-button>
</form>
</div>
</header>
<main id="contenido" class="mx-auto max-w-6xl px-6 py-10 lg:px-12 lg:py-12">{{ $slot }}</main>
</div>
</div>
</x-layouts.base>
