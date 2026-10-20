@props(['variant' => 'status'])
<div role="{{ $variant === 'error' ? 'alert' : 'status' }}" {{ $attributes->class(['rounded-xl border px-4 py-3 text-sm leading-6', 'border-red-200 bg-red-50 text-red-800' => $variant === 'error', 'border-emerald/20 bg-emerald/5 text-ink' => $variant !== 'error']) }}>{{ $slot }}</div>
