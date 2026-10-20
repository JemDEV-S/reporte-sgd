<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['codigo', 'nombre', 'dependencia_padre_id', 'nivel', 'activa'])]
class Dependencia extends Model
{
    protected $table = 'dependencias';

    protected function casts(): array
    {
        return [
            'nivel' => 'integer',
            'activa' => 'boolean',
        ];
    }

    public function padre(): BelongsTo
    {
        return $this->belongsTo(self::class, 'dependencia_padre_id');
    }

    public function hijas(): HasMany
    {
        return $this->hasMany(self::class, 'dependencia_padre_id');
    }

    public function empleados(): HasMany
    {
        return $this->hasMany(Empleado::class, 'dependencia_id');
    }
}
