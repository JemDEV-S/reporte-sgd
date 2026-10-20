<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['publicacion_id', 'codigo', 'nombre', 'encargado_codigo', 'baja'])]
class DependenciaCaptura extends Model
{
    protected $table = 'dependencias_captura';

    public function publicacion(): BelongsTo
    {
        return $this->belongsTo(Publicacion::class, 'publicacion_id');
    }
}
