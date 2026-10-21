<?php

namespace App\Http\Requests\Administracion;

use App\Enums\PermisoCodigo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarRolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('roles.gestionar');
    }

    public function rules(): array
    {
        return [
            // Los códigos existentes son identidades estables, no se renombran.
            'codigo' => $this->route('rol') ? ['prohibited'] : ['required', 'string', 'max:80', 'regex:/^[a-z][a-z0-9_]*$/', 'unique:roles,codigo'],
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'permisos' => ['sometimes', 'array', 'max:100'],
            'permisos.*' => ['required', 'integer', 'distinct', Rule::exists('permisos', 'id')->whereIn('codigo', array_column(PermisoCodigo::cases(), 'value'))],
        ];
    }
}
