<?php

namespace App\Console\Commands;

use App\Ingesta\ProgramarSincronizacion;
use App\Models\ConfiguracionSincronizacion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class PlanificarSincronizacion extends Command
{
    protected $signature = 'sgd:planificar';

    protected $description = 'Registra actividad del scheduler y encola la sincronización cuando corresponde';

    public function handle(ProgramarSincronizacion $programador): int
    {
        if (! Schema::hasTable('sincronizacion_sgd')) {
            return self::SUCCESS;
        }
        ConfiguracionSincronizacion::whereKey(1)->update(['scheduler_visto_en' => now()]);
        $programador->recuperarInterrumpidas();
        $programador->solicitar(automatica: true);

        return self::SUCCESS;
    }
}
