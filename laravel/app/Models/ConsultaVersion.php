<?php

namespace App\Models;

use App\Explorador\ConsultaExplorador;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Versión inmutable de una consulta guardada. Guarda la especificación
 * declarativa (agrupaciones, filtros, dependencia, orden); nunca SQL. Cambiar
 * una consulta crea una versión nueva en lugar de alterar esta.
 */
#[Fillable(['consulta_id', 'numero', 'agrupaciones', 'filtros', 'dependencia', 'orden'])]
class ConsultaVersion extends Model
{
    protected $table = 'consulta_versiones';

    protected function casts(): array
    {
        return [
            'numero' => 'integer',
            'agrupaciones' => 'array',
            'filtros' => 'array',
        ];
    }

    public function consulta(): BelongsTo
    {
        return $this->belongsTo(ConsultaGuardada::class, 'consulta_id');
    }

    /** Reconstruye la especificación ejecutable, revalidada contra el catálogo. */
    public function aConsultaExplorador(): ConsultaExplorador
    {
        return ConsultaExplorador::desde([
            'agrupaciones' => $this->agrupaciones ?? [],
            'filtros' => $this->filtros ?? [],
            'dependencia' => $this->dependencia,
            'orden' => $this->orden,
        ]);
    }
}
