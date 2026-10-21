<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfiguracionSincronizacion extends Model
{
    protected $table = 'sincronizacion_sgd';

    protected function casts(): array
    {
        return [
            'automatica' => 'boolean', 'intervalo_minutos' => 'integer',
            'proxima_ejecucion_en' => 'immutable_datetime',
            'scheduler_visto_en' => 'immutable_datetime', 'worker_visto_en' => 'immutable_datetime',
        ];
    }
}
