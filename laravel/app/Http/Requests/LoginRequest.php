<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['dni' => ['required', 'string', 'regex:/^[0-9]{8}$/'], 'password' => ['required', 'string', 'max:1024']];
    }

    public function messages(): array
    {
        return [
            'dni.required' => 'Ingresa tu DNI.',
            'dni.string' => 'Ingresa un DNI de ocho dígitos.',
            'dni.regex' => 'El DNI debe contener ocho dígitos.',
            'password.required' => 'Ingresa tu contraseña.',
            'password.string' => 'Ingresa una contraseña válida.',
            'password.max' => 'La contraseña es demasiado larga.',
        ];
    }

    public function authenticate(): void
    {
        $key = 'login:'.hash('sha256', $this->string('dni').'|'.$this->ip());

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['dni' => 'Demasiados intentos. Intenta nuevamente en '.RateLimiter::availableIn($key).' segundos.']);
        }

        if (! Auth::guard('web')->attemptWhen(
            $this->only('dni', 'password'),
            fn (User $user) => Gate::forUser($user)->allows('plataforma.acceder'),
        )) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['dni' => 'No se pudo iniciar sesión con las credenciales proporcionadas.']);
        }

        RateLimiter::clear($key);
    }
}
