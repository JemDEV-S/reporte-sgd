@props(['titulo', 'descripcion' => null, 'noLeidos', 'recibidos'])
<div {{ $attributes->class(['rounded-xl border border-line bg-white p-3 sm:p-4']) }}>
    <div class="flex items-center justify-between gap-3">
        <h3 class="flex items-center gap-2 text-sm font-semibold"><x-icon :name="str_contains(mb_strtolower($titulo), 'entrada') ? 'inbox' : 'users'" class="size-4 text-emerald" />{{ $titulo }}</h3>
        <span class="text-xl font-semibold tabular-nums">{{ number_format($noLeidos + $recibidos) }}</span>
    </div>
    <dl class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3 border-t border-line pt-2 text-xs text-muted">
        <div><dt>No leídos</dt><dd class="mt-1 text-base font-semibold tabular-nums text-ink">{{ number_format($noLeidos) }}</dd></div>
        <div><dt>Recibidos sin atender</dt><dd class="mt-1 text-base font-semibold tabular-nums text-ink">{{ number_format($recibidos) }}</dd></div>
    </dl>
</div>
