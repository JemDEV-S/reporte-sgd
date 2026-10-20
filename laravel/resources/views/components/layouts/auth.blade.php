<x-layouts.base title="Iniciar sesión">
<div class="grid min-h-screen grid-rows-[auto_1fr] lg:grid-cols-2 lg:grid-rows-1">
<aside class="relative flex flex-col overflow-hidden bg-[#142b35] px-7 py-6 text-white sm:px-12 lg:p-16">
<x-brand />
<div class="relative z-10 my-auto hidden max-w-lg py-20 lg:block">
<p class="eyebrow text-mint">INFORMACIÓN CON PROPÓSITO</p>
<h2 class="mt-6 text-4xl leading-[1.12] font-medium tracking-tight sm:text-5xl xl:text-6xl">Una mirada clara.<br>
<span class="text-mint">Mejores decisiones.</span>
</h2>
<p class="mt-7 max-w-sm text-base leading-7 text-slate-300">Reportes, análisis e inteligencia documental al servicio de nuestra institución.</p>
<div class="mt-12 grid grid-cols-2 gap-x-8 gap-y-5 border-t border-white/15 pt-6 text-sm text-slate-300">
@foreach (config('platform.areas') as $item)<p>
<span class="mr-2 text-mint" aria-hidden="true">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>{{ $item['name'] }}</p>@endforeach
</div>
</div>
<p class="relative z-10 mt-4 text-xs leading-5 text-slate-400 lg:mt-0">Municipalidad Distrital de San Jerónimo<span class="hidden lg:block">Plataforma institucional de información</span></p>
<x-illustration class="pointer-events-none absolute -right-12 -bottom-4 hidden w-80 opacity-25 lg:block" />
</aside>
<main id="contenido" class="flex flex-col justify-between px-6 py-8 sm:px-12 lg:px-16">
<p class="text-right text-xs font-medium tracking-wide text-muted">ACCESO INSTITUCIONAL <span class="ml-2 inline-block size-1.5 rounded-full bg-emerald" aria-hidden="true">
</span>
</p>
<div class="mx-auto w-full max-w-sm py-8 lg:py-14">{{ $slot }}</div>
<p class="text-center text-xs text-muted">SIRMD · Un espacio para comprender y decidir.</p>
</main>
</div>
</x-layouts.base>
