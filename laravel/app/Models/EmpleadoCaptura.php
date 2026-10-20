<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'publicacion_id', 'codigo', 'apellido_paterno', 'apellido_materno',
    'denominacion', 'estado_empleado_codigo', 'baja_codigo',
    'dependencia_principal_codigo', 'dependencia_configurada_codigo',
])]
class EmpleadoCaptura extends Model
{
    protected $table = 'empleados_captura';

    public function publicacion(): BelongsTo
    {
        return $this->belongsTo(Publicacion::class, 'publicacion_id');
    }

    /** Empleado activo según la regla verificada en la fuente (CEMP_EST_EMP='1'). */
    public function esActivo(): bool
    {
        return $this->estado_empleado_codigo === '1';
    }

    /**
     * Nombre legible "APELLIDOS, Denominación", omitiendo partes ausentes.
     * El nombre es dato personal: solo debe mostrarse con permiso.
     */
    public function nombreCompleto(): string
    {
        $apellidos = trim(implode(' ', array_filter([
            $this->apellido_paterno,
            $this->apellido_materno,
        ])));

        $partes = array_filter([$apellidos, $this->denominacion]);

        return $partes === [] ? '' : implode(', ', $partes);
    }
}
