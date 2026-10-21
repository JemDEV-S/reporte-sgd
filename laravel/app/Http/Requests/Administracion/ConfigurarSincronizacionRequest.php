<?php

namespace App\Http\Requests\Administracion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfigurarSincronizacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('fuentes.gestionar');
    }

    public function rules(): array
    {
        return ['automatica' => ['required', 'boolean'], 'intervalo_minutos' => ['required', 'integer', Rule::in([5, 10, 15, 30, 60])]];
    }

    public function messages(): array
    {
        return ['intervalo_minutos.in' => 'Selecciona una frecuencia de 5, 10, 15, 30 o 60 minutos.'];
    }
}
