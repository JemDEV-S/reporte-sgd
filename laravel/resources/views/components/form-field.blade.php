@props(['name', 'label', 'type' => 'text', 'hint' => null, 'value' => null])
@php($displayValue = old($name, $value))
<div>
<label for="{{ $name }}" class="mb-2 block text-sm font-semibold">{{ $label }}</label>
<div class="relative">
<input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" 
@if ($type !== 'password') value="{{ is_scalar($displayValue) ? $displayValue : '' }}" @endif
 @error($name) aria-invalid="true" @enderror 
@if ($errors->has($name) || $hint) aria-describedby="{{ $name }}-help" @endif
 {{ $attributes->class(['field', 'pr-24' => $type === 'password']) }}>
@if ($type === 'password')<button type="button" data-password-toggle="{{ $name }}" aria-label="Mostrar contraseña" aria-controls="{{ $name }}" aria-pressed="false" class="absolute inset-y-1 right-1 rounded-md px-3 text-xs font-semibold text-emerald hover:bg-emerald/5">Mostrar</button>@endif
</div>
@if ($errors->has($name) || $hint)<p id="{{ $name }}-help" @class(['mt-2 text-xs leading-5', 'text-red-700' => $errors->has($name), 'text-muted' => ! $errors->has($name)]) 
@if ($errors->has($name)) role="alert" @endif
>{{ $errors->first($name) ?: $hint }}</p>@endif
</div>
