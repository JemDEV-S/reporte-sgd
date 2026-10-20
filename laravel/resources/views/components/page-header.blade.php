@props(['eyebrow' => 'ESPACIO DE TRABAJO', 'title', 'description' => null])
<header class="mb-9 max-w-2xl">
<p class="eyebrow text-emerald">{{ $eyebrow }}</p>
<h1 class="mt-3 text-3xl font-medium tracking-tight sm:text-4xl">{{ $title }}</h1>
@if ($description)<p class="mt-4 leading-7 text-muted">{{ $description }}</p>@endif
</header>
