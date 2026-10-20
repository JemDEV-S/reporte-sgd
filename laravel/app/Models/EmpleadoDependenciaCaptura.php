<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'publicacion_id', 'empleado_codigo', 'dependencia_codigo',
    'es_principal', 'es_configurada', 'es_asignada', 'es_activa',
])]
class EmpleadoDependenciaCaptura extends Model
{
    protected $table = 'empleado_dependencia_captura';

    protected function casts(): array
    {
        return [
            'es_principal' => 'boolean',
            'es_configurada' => 'boolean',
            'es_asignada' => 'boolean',
            'es_activa' => 'boolean',
        ];
    }

    public function publicacion(): BelongsTo
    {
        return $this->belongsTo(Publicacion::class, 'publicacion_id');
    }
}
