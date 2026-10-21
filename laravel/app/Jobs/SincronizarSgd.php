<?php

namespace App\Jobs;

use App\Ingesta\EjecutarSincronizacion;
use App\Ingesta\IngestaOcupada;
use App\Models\SincronizacionSgd;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SincronizarSgd implements ShouldQueue
{
    use Queueable;

    public int $tries = 0;

    public int $maxExceptions = 1;

    public int $timeout = 3600;

    public bool $failOnTimeout = true;

    public function __construct(public readonly int $ejecucionId)
    {
        $this->onConnection('ingesta')->onQueue('sgd');
    }

    public function handle(EjecutarSincronizacion $ejecutor): void
    {
        try {
            $ejecutor->ejecutar($this->ejecucionId);
        } catch (IngestaOcupada $error) {
            $this->release(10);
        }
    }

    public function failed(?Throwable $error): void
    {
        // Solo pendientes: una ejecución larga en Windows puede conservar el bloqueo
        // aunque otro worker detecte el vencimiento de la reserva de la cola.
        SincronizacionSgd::whereKey($this->ejecucionId)->where('estado', 'pendiente')->update([
            'estado' => 'fallida', 'activa' => null, 'fin_en' => now(),
            'error_resumen' => 'El trabajo no pudo iniciarse. Revisa el servicio de sincronización.',
        ]);
    }
}
