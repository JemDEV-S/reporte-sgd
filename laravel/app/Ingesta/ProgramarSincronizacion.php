<?php

namespace App\Ingesta;

use App\Administracion\RegistrarAuditoria;
use App\Jobs\SincronizarSgd;
use App\Models\ConfiguracionSincronizacion;
use App\Models\SincronizacionSgd;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

class ProgramarSincronizacion
{
    public function __construct(private readonly RegistrarAuditoria $auditoria, private readonly BloqueoIngesta $bloqueo) {}

    public function configurar(User $actor, array $datos): void
    {
        DB::transaction(function () use ($actor, $datos) {
            $config = ConfiguracionSincronizacion::lockForUpdate()->findOrFail(1);
            Gate::forUser($actor)->authorize('plataforma.acceder');
            Gate::forUser($actor)->authorize('fuentes.gestionar');
            $antes = ['automatica' => $config->automatica, 'intervalo_minutos' => $config->intervalo_minutos];
            $config->automatica = (bool) $datos['automatica'];
            $config->intervalo_minutos = (int) $datos['intervalo_minutos'];
            if ($config->isDirty(['automatica', 'intervalo_minutos'])) {
                $config->proxima_ejecucion_en = $config->automatica ? now()->addMinutes($config->intervalo_minutos) : null;
            }
            $config->save();
            $this->auditoria->registrar($actor, 'sincronizacion.configurada', 'sincronizacion', 1, [
                'antes' => $antes,
                'despues' => ['automatica' => $config->automatica, 'intervalo_minutos' => $config->intervalo_minutos],
            ]);
        });
    }

    public function solicitar(?User $actor = null, bool $automatica = false, bool $encolar = true, string $tipo = 'incremental', ?string $dependencia = null): ?SincronizacionSgd
    {
        return DB::transaction(function () use ($actor, $automatica, $encolar, $tipo, $dependencia) {
            $config = ConfiguracionSincronizacion::lockForUpdate()->findOrFail(1);
            if ($actor !== null) {
                Gate::forUser($actor)->authorize('plataforma.acceder');
                Gate::forUser($actor)->authorize('fuentes.gestionar');
            }
            if ($automatica && (! $config->automatica || $config->proxima_ejecucion_en?->isFuture())) {
                return null;
            }
            $activa = SincronizacionSgd::where('activa', 1)->first();
            if ($activa) {
                if (! $encolar) {
                    throw ValidationException::withMessages(['sincronizacion' => 'Ya existe una sincronización pendiente o en curso.']);
                }

                return $activa;
            }
            $ejecucion = SincronizacionSgd::create([
                'origen' => $automatica ? 'automatica' : ($actor ? 'manual' : 'consola'),
                'tipo' => $tipo, 'dependencia' => $dependencia, 'user_id' => $actor?->id,
                'estado' => 'pendiente', 'activa' => 1,
            ]);
            if ($encolar) {
                // La cola database usa la misma conexión: reserva y trabajo se confirman juntos.
                Queue::connection('ingesta')->push(new SincronizarSgd($ejecucion->id), '', 'sgd');
            }
            if ($automatica) {
                $config->proxima_ejecucion_en = now()->addMinutes($config->intervalo_minutos);
                $config->save();
            }
            if ($actor) {
                $this->auditoria->registrar($actor, 'sincronizacion.solicitada', 'sincronizacion', $ejecucion->id, ['tipo' => 'incremental']);
            }

            return $ejecucion;
        });
    }

    public function recuperarInterrumpidas(): void
    {
        $interrumpidas = fn () => SincronizacionSgd::where('estado', 'ejecutando')
            ->orWhere(fn ($query) => $query->where('estado', 'pendiente')->where('origen', 'consola')->where('created_at', '<', now()->subMinutes(2)));
        if (! $interrumpidas()->exists()) {
            return;
        }
        try {
            $this->bloqueo->ejecutar(function () use ($interrumpidas) {
                // Si adquirimos el bloqueo, ninguna ingesta sigue ejecutándose.
                $interrumpidas()->update([
                    'estado' => 'interrumpida', 'activa' => null, 'fin_en' => now(),
                    'error_resumen' => 'El proceso terminó antes de confirmar su resultado. Revisa la publicación vigente.',
                ]);
            });
        } catch (IngestaOcupada $error) {
            // Una ingesta conserva el bloqueo: no se interrumpe por exceder un tiempo fijo.
        }
    }
}
