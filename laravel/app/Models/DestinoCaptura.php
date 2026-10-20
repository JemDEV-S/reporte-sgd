<?php

namespace App\Models;

use App\Enums\Bandeja;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'publicacion_id', 'anio', 'emision', 'destino', 'tipo_destino',
    'dependencia_codigo', 'destinatario_empleado', 'encargado_empleado',
    'estado_recepcion', 'activo', 'prioridad', 'fecha_recepcion',
])]
class DestinoCaptura extends Model
{
    protected $table = 'destinos_captura';

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'fecha_recepcion' => 'datetime',
        ];
    }

    public function publicacion(): BelongsTo
    {
        return $this->belongsTo(Publicacion::class, 'publicacion_id');
    }

    /**
     * Clasificación de bandeja: Entrada si el destinatario coincide con el
     * encargado capturado; Delegados en caso contrario.
     */
    public function bandeja(): Bandeja
    {
        return $this->destinatario_empleado === $this->encargado_empleado
            ? Bandeja::Entrada
            : Bandeja::Delegados;
    }
}
