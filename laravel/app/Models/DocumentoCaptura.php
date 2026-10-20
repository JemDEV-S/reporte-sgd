<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'publicacion_id', 'anio', 'emision', 'tipo_documental',
    'estado_emision', 'eliminado', 'fecha_emision',
    // Metadatos documentales legibles (mejora del detalle, §4).
    'tipo_emisor', 'numero_documento', 'numero_emisor', 'sigla_documento',
    'numero_incompleto', 'asunto', 'dependencia_emisora_codigo',
    'emisor_empleado_codigo', 'origen_descripcion',
])]
class DocumentoCaptura extends Model
{
    protected $table = 'documentos_captura';

    protected function casts(): array
    {
        return [
            'eliminado' => 'boolean',
            'numero_incompleto' => 'boolean',
            'fecha_emision' => 'datetime',
        ];
    }

    public function publicacion(): BelongsTo
    {
        return $this->belongsTo(Publicacion::class, 'publicacion_id');
    }
}
