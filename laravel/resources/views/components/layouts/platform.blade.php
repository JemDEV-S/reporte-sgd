@props(['areas', 'area', 'title' => null])
<x-layouts.base :title="$title ?? ($area ? $areas[$area]['name'] : 'Inicio')">
<div class="platform-shell min-h-screen lg:grid lg:grid-cols-[240px_minmax(0,1fr)]">
<aside class="platform-sidebar border-r border-white/5 bg-[#142b35] text-white">
<div class="flex flex-col px-4 py-5 lg:sticky lg:top-0 lg:h-dvh lg:py-7">
<a href="{{ route('home') }}" class="mb-6 block px-2" aria-label="SIRMD, inicio"><x-brand /></a>
<div class="hidden lg:block"><x-navigation :areas="$areas" :area="$area" /></div>
<details class="lg:hidden">
<summary class="min-h-11 cursor-pointer rounded-xl border border-white/15 px-3 py-2 text-sm">Menú de navegación</summary>
<div class="pt-3"><x-navigation :areas="$areas" :area="$area" /></div>
</details>
<div class="mt-auto hidden rounded-xl border border-white/10 bg-white/5 p-4 lg:block">
<x-icon name="shield" class="mb-3 size-5 text-mint" />
<p class="text-xs font-medium text-slate-200">Espacio institucional</p>
<p class="mt-1 text-xs leading-5 text-slate-400">Municipalidad Distrital<br>de San Jerónimo</p>
</div>
</div>
</aside>
<div class="min-w-0">
<header class="platform-topbar flex min-h-14 flex-wrap items-center justify-between gap-3 border-b border-line bg-white px-4 py-2 sm:px-6 lg:px-8">
<p class="flex items-center gap-2 text-xs text-muted"><span class="font-semibold tracking-widest text-ink">SIRMD</span><span class="text-slate-300" aria-hidden="true">/</span>{{ $title ?? ($area ? $areas[$area]['name'] : 'Inicio') }}</p>
<div class="flex items-center gap-3 sm:gap-5">
<span class="hidden size-9 items-center justify-center rounded-full bg-emerald/10 text-sm font-semibold text-emerald sm:inline-flex" aria-hidden="true">{{ mb_substr(auth()->user()->nombre_completo, 0, 1) }}</span>
<span class="max-w-40 truncate text-sm font-medium sm:max-w-56">{{ auth()->user()->nombre_completo }}</span>
<form method="POST" action="{{ route('logout') }}">@csrf<x-button variant="quiet"><x-icon name="logout" class="size-4" /><span>Cerrar sesión</span></x-button></form>
</div>
</header>
<main id="contenido" class="workspace">{{ $slot }}</main>
</div>
</div>
</x-layouts.base>
