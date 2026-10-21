@props(['title'])
@php
    $areas = collect(config('platform.areas'))->map(fn ($item) => [...$item, 'allowed' => auth()->user()->can($item['permission'])]);
@endphp
<x-layouts.platform :areas="$areas" :area="null" :title="$title">
    <div class="mb-6"><p class="eyebrow text-emerald">ADMINISTRACIÓN</p><h1 class="mt-2 text-2xl font-semibold">{{ $title }}</h1></div>
    @if(session('estado'))<x-alert class="mb-5">{{ session('estado') }}</x-alert>@endif
    @if($errors->any())
        <div role="alert" class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <p class="font-semibold">Revisa los datos antes de guardar.</p>
            <ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    {{ $slot }}
</x-layouts.platform>
