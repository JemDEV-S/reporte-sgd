@props(['name' => 'chart'])
@php
    $paths = [
        'home' => 'm3 10 9-7 9 7M5 9v12h5v-7h4v7h5V9',
        'chart' => 'M4 4v16h16M8 15l4-5 4 3 4-7',
        'reportes' => 'M6 3h9l4 4v14H6zM14 3v5h5M9 12h7M9 16h5',
        'explorador' => 'M10 3a7 7 0 1 0 0 14 7 7 0 0 0 0-14m5 12 6 6',
        'informes' => 'M5 3h14v18H5zM8 7h8M8 11h8M8 15h3M14 15h2M8 18h8',
        'building' => 'M4 21V7l8-4 8 4v14M2 21h20M9 21v-5h6v5M8 8h1M15 8h1M8 12h1M15 12h1',
        'inbox' => 'M4 4h16l2 12v4H2v-4L4 4zM2 15h6l2 3h4l2-3h6',
        'users' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 3a4 4 0 1 0 0 8 4 4 0 0 0 0-8M17 4a4 4 0 0 1 0 8M22 21v-2a4 4 0 0 0-3-4',
        'arrow' => 'M5 12h14m-6-6 6 6-6 6',
        'download' => 'M12 3v12m-5-5 5 5 5-5M4 16v5h16v-5',
        'logout' => 'M9 4H4v16h5M10 12h11m-4-4 4 4-4 4',
        'filter' => 'M3 4h18l-7 8v7l-4 2v-9L3 4z',
        'clock' => 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18m0 4v5l4 2',
        'shield' => 'm12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6l8-3zm-4 9 3 3 5-6',
    ];
@endphp
<svg {{ $attributes->class(['size-5 shrink-0']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    <path d="{{ $paths[$name] ?? $paths['chart'] }}" />
</svg>
