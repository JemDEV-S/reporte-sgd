<x-layouts.platform :areas="$areas" :area="$area">
@if ($area)
<x-page-header eyebrow="ÁREAS DE LA PLATAFORMA" :title="$areas[$area]['name']" :description="$areas[$area]['description']" />
@if ($area === 'reportes')
<div class="mb-8 flex flex-wrap items-center justify-between gap-3 border-y border-line py-4">
<h2 class="text-sm font-semibold">Reportes disponibles</h2>
<span class="status-label">Implementación progresiva</span>
</div>
<section aria-label="reportes del catálogo" class="grid gap-5 md:grid-cols-2">
<article class="area-card group">
<div class="flex items-center justify-between">
<span class="icon-tile"><x-icon :name="$area" class="size-6" /></span>
<span class="h-px w-12 bg-line" aria-hidden="true"></span>
</div>
<h3 class="mt-7 text-xl font-medium">Pendientes por dependencia</h3>
<p class="mt-3 flex-1 text-sm leading-7 text-muted">Documentos no leídos y recibidos sin atender, en entrada y delegados, recorriendo la institución hasta el personal responsable.</p>
<div class="mt-7 border-t border-line pt-4">
<a class="flex items-center justify-between text-sm font-semibold text-emerald" href="{{ route('reportes.pendientes-dependencia') }}">
<span>Abrir reporte</span><x-icon name="arrow" class="size-4" />
</a>
</div>
</article>
<article class="area-card opacity-60">
<div class="flex items-center justify-between">
<span class="icon-tile"><x-icon name="clock" class="size-6" /></span>
<span class="h-px w-12 bg-line" aria-hidden="true"></span>
</div>
<h3 class="mt-7 text-xl font-medium">Próximos reportes</h3>
<p class="mt-3 flex-1 text-sm leading-7 text-muted">Productividad, movimientos y tiempos de atención se incorporarán sobre el mismo motor de ejecuciones.</p>
<div class="mt-7 border-t border-line pt-4"><p class="text-xs text-muted">En preparación</p></div>
</article>
</section>
@elseif ($area === 'explorador')
<div class="mb-8 flex flex-wrap items-center justify-between gap-3 border-y border-line py-4">
<h2 class="text-sm font-semibold">Explorador disponible</h2>
<span class="status-label">Beta</span>
</div>
<section aria-label="herramientas del explorador" class="grid gap-5 md:grid-cols-2">
<article class="area-card group">
<div class="flex items-center justify-between">
<span class="icon-tile"><x-icon :name="$area" class="size-6" /></span>
<span class="h-px w-12 bg-line" aria-hidden="true"></span>
</div>
<h3 class="mt-7 text-xl font-medium">Pendientes: agrupar y filtrar</h3>
<p class="mt-3 flex-1 text-sm leading-7 text-muted">Consulta dinámica sobre los destinos pendientes de la publicación vigente: elige campos para agrupar, aplica filtros y guarda tus consultas. Comparte las reglas y el universo del reporte.</p>
<div class="mt-7 border-t border-line pt-4">
<a class="flex items-center justify-between text-sm font-semibold text-emerald" href="{{ route('explorador.index') }}">
<span>Abrir explorador</span><x-icon name="arrow" class="size-4" />
</a>
</div>
</article>
</section>
@elseif ($area === 'informes')
<div class="mb-8 flex flex-wrap items-center justify-between gap-3 border-y border-line py-4">
<h2 class="text-sm font-semibold">Informes disponibles</h2>
<span class="status-label">Beta</span>
</div>
<section aria-label="informes inteligentes" class="grid gap-5 md:grid-cols-2">
<article class="area-card group">
<div class="flex items-center justify-between">
<span class="icon-tile"><x-icon :name="$area" class="size-6" /></span>
<span class="h-px w-12 bg-line" aria-hidden="true"></span>
</div>
<h3 class="mt-7 text-xl font-medium">Informe situacional de pendientes</h3>
<p class="mt-3 flex-1 text-sm leading-7 text-muted">Memorando redactado a partir de agregados verificados del motor (resumen, ranking y cuadros por gerencia). Nace como borrador, requiere aprobación humana y se descarga en Word.</p>
<div class="mt-7 border-t border-line pt-4">
<a class="flex items-center justify-between text-sm font-semibold text-emerald" href="{{ route('informes.index') }}">
<span>Abrir informes</span><x-icon name="arrow" class="size-4" />
</a>
</div>
</article>
</section>
@else
<section class="rounded-2xl border border-line bg-white px-7 py-12 sm:p-12">
<span class="status-label">En preparación</span>
<h2 class="mt-5 text-xl font-medium">Este espacio está tomando forma</h2>
<p class="mt-3 max-w-lg text-sm leading-7 text-muted">Las funcionalidades de esta área se incorporarán progresivamente. Cuando estén disponibles, podrás trabajar con la información autorizada para tu cuenta.</p>
<a href="{{ route('home') }}" class="mt-8 inline-block text-sm font-semibold text-emerald">← Volver al inicio</a>
</section>
@endif
@else

<section class="panel mb-7 flex items-center justify-between gap-6 overflow-hidden">
<div class="max-w-2xl"><p class="eyebrow text-emerald">TU ESPACIO DE TRABAJO</p><h1 class="mt-3 text-2xl font-semibold tracking-tight sm:text-3xl">Información que orienta decisiones.</h1><p class="mt-3 text-sm leading-7 text-muted">Un punto de encuentro para comprender la actividad documentaria, explorar información y construir conocimiento institucional.</p></div>
<x-illustration class="hidden w-64 shrink-0 lg:block" />
</section>
<div class="mb-8 flex flex-wrap items-center justify-between gap-3 border-y border-line py-4">
<h2 class="text-sm font-semibold">Las cuatro áreas del SIRMD</h2>
<span class="status-label">Implementación progresiva</span>
</div>
<section aria-label="áreas de la plataforma" class="grid gap-5 md:grid-cols-2">
@foreach ($areas as $key => $item)
<article class="area-card group">
<div class="flex items-center justify-between">
<span class="icon-tile"><x-icon :name="$key === 'analisis' ? 'chart' : $key" class="size-6" /></span>
<span class="h-px w-12 bg-line" aria-hidden="true">
</span>
</div>
<h3 class="mt-7 text-xl font-medium">{{ $item['name'] }}</h3>
<p class="mt-3 flex-1 text-sm leading-7 text-muted">{{ $item['description'] }}</p>
<div class="mt-7 border-t border-line pt-4">
@if ($item['allowed'])<a class="flex items-center justify-between text-sm font-semibold text-emerald" href="{{ route('platform.area', $key) }}">
<span>Conocer el área<span class="sr-only">: {{ $item['name'] }}</span>
</span>
<x-icon name="arrow" class="size-4" />
</a>@else
<p class="text-xs text-muted">Requiere permiso de acceso</p>@endif

</div>
</article>@endforeach
</section>
@can('usuarios.gestionar')<x-alert class="mt-7">
<strong class="font-semibold">Administración técnica.</strong> Tu perfil permite administrar la plataforma. El acceso documental requiere permisos específicos. La gestión web de cuentas se incorporará en una próxima etapa.</x-alert>@endcan

<footer class="mt-10 flex flex-wrap justify-between gap-3 text-xs text-muted">
<span>Información institucional · Acceso según permisos</span>
<span>Municipalidad Distrital de San Jerónimo</span>
</footer>
@endif
</x-layouts.platform>
