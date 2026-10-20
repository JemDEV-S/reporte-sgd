<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Consulta guardada del explorador. Pertenece a un autor y acumula versiones;
 * su visibilidad controla quién puede verla, pero nunca amplía el alcance de
 * datos, que se calcula al ejecutar.
 */
#[Fillable(['creado_por', 'nombre', 'reporte', 'visibilidad'])]
class ConsultaGuardada extends Model
{
    protected $table = 'consultas_guardadas';

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function versiones(): HasMany
    {
        return $this->hasMany(ConsultaVersion::class, 'consulta_id');
    }

    /** Última versión de la consulta (la mayor por número). */
    public function versionVigente(): ?ConsultaVersion
    {
        return $this->versiones()->orderByDesc('numero')->first();
    }

    public function esInstitucional(): bool
    {
        return $this->visibilidad === 'institucional';
    }
}
