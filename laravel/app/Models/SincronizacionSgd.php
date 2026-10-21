<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SincronizacionSgd extends Model
{
    protected $table = 'sincronizaciones_sgd';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['inicio_en' => 'immutable_datetime', 'fin_en' => 'immutable_datetime', 'metricas' => 'array'];
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
