@props([
    'normal' => 0,
    'urgente' => 0,
    'muyUrgente' => 0,
])
@php
    $total = $normal + $urgente + $muyUrgente;
    // Cada prioridad lleva SIEMPRE su etiqueta y conteo: el color del SGD
    // (azul/naranja/rojo) no basta para distinguirlas de forma accesible.
    $segmentos = [
        ['nombre' => 'Normal', 'valor' => $normal, 'clase' => 'bg-blue-600'],
        ['nombre' => 'Urgente', 'valor' => $urgente, 'clase' => 'bg-orange-500'],
        ['nombre' => 'Muy urgente', 'valor' => $muyUrgente, 'clase' => 'bg-red-600'],
    ];
@endphp
<div {{ $attributes }}>
    <div class="flex h-2.5 overflow-hidden rounded-full bg-paper" role="img"
         aria-label="Prioridades: {{ $normal }} normal, {{ $urgente }} urgente, {{ $muyUrgente }} muy urgente">
        @foreach ($segmentos as $s)
            @if ($s['valor'] > 0)
                <span class="{{ $s['clase'] }} border-r-2 border-white last:border-r-0"
                      style="width: {{ $total > 0 ? round($s['valor'] / $total * 100, 2) : 0 }}%"></span>
            @endif
        @endforeach
    </div>
    <ul class="mt-2.5 flex flex-wrap gap-x-4 gap-y-1 text-[11px] text-muted">
        @foreach ($segmentos as $s)
            <li class="inline-flex items-center gap-1.5">
                <span class="{{ $s['clase'] }} inline-block h-2.5 w-2.5 rounded-sm" aria-hidden="true"></span>
                <span>{{ $s['nombre'] }}</span>
                <span class="font-semibold tabular-nums text-ink">{{ number_format($s['valor']) }}</span>
            </li>
        @endforeach
    </ul>
</div>
