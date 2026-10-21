<?php

namespace App\Http\Requests\Administracion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class GuardarUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('usuarios.gestionar');
    }

    protected function prepareForValidation(): void
    {
        foreach (['email', 'nombre', 'apellidos'] as $campo) {
            $valor = $this->input($campo);
            if (is_string($valor)) {
                $this->merge([$campo => $campo === 'email' ? Str::lower(trim($valor)) : Str::squish($valor)]);
            }
        }
    }

    public function rules(): array
    {
        $usuario = $this->route('usuario');

        return [
            'dni' => ['required', 'string', 'regex:/^[0-9]{8}$/', Rule::unique('users', 'dni')->ignore($usuario)],
            'nombre' => ['required', 'string', 'max:255'],
            'apellidos' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($usuario)],
            'activo' => ['required', 'boolean'],
            'password' => [$usuario ? 'nullable' : 'required', 'string', 'max:72', 'confirmed', Password::min(6)->letters()->numbers()],
            'roles' => ['sometimes', 'array', 'max:100'],
            'roles.*' => ['required', 'integer', 'distinct', 'exists:roles,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'unique' => 'El campo :attribute ya está registrado.',
            'dni.regex' => 'El DNI debe contener ocho dígitos.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'password.min' => 'La contraseña debe tener al menos 12 caracteres.',
            'password.letters' => 'La contraseña debe incluir letras.',
            'password.numbers' => 'La contraseña debe incluir números.',
        ];
    }
}
