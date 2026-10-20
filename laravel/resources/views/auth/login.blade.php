<x-layouts.auth>
<p class="eyebrow text-emerald">BIENVENIDO AL SIRMD</p>
<h1 class="mt-3 text-3xl font-medium tracking-tight">Inicia sesión</h1>
<p class="mt-3 text-sm leading-6 text-muted">Ingresa con tu cuenta institucional para acceder a tu espacio de trabajo.</p>
@if (session('status'))<x-alert class="mt-6">{{ session('status') }}</x-alert>@endif

<form method="POST" action="{{ route('login.store') }}" class="mt-8 space-y-6">@csrf
<x-form-field name="dni" label="DNI" inputmode="numeric" pattern="[0-9]{8}" maxlength="8" autocomplete="username" placeholder="Tu documento de 8 dígitos" required autofocus />
<x-form-field name="password" label="Contraseña" type="password" autocomplete="current-password" placeholder="Ingresa tu contraseña" required />
<x-button class="w-full justify-between">
<span>Ingresar a la plataforma</span>
<span aria-hidden="true">→</span>
</x-button>
</form>
<div class="mt-9 border-t border-line pt-6 text-xs leading-6 text-muted">
<p class="font-semibold text-ink">¿Necesitas acceso?</p>
<p>Comunícate con la administración del SIRMD. Las cuentas se habilitan de forma institucional.</p>
</div>
</x-layouts.auth>
