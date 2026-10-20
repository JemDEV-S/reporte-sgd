@props([
    'titulo',
    'descripcion' => null,
    'noLeidos',
    'recibidos',
])
@php($total = $noLeidos + $recibidos)
<div {{ $attributes->merge(['class' => 'rounded-2xl border border-line bg-white p-6']) }}>
    <div class="flex items-baseline justify-between gap-3">
        <h3 class="text-sm font-semibold text-ink">{{ $titulo }}</h3>
        <span class="text-2xl font-light tabular-nums text-ink">{{ number_format($total) }}</span>
    </div>
    @if ($descripcion)
        <p class="mt-1 text-xs leading-5 text-muted">{{ $descripcion }}</p>
    @endif
    <dl class="mt-5 grid grid-cols-2 gap-3">
        <div class="rounded-xl bg-paper px-4 py-3">
            <dt class="text-[11px] font-medium text-muted">No leídos</dt>
            <dd class="mt-1 text-xl font-medium tabular-nums text-ink">{{ number_format($noLeidos) }}</dd>
        </div>
        <div class="rounded-xl bg-paper px-4 py-3">
            <dt class="text-[11px] font-medium text-muted">Recibidos sin atender</dt>
            <dd class="mt-1 text-xl font-medium tabular-nums text-ink">{{ number_format($recibidos) }}</dd>
        </div>
    </dl>
</div>
