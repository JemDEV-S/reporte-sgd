<?php

namespace App\Explorador;

use App\Models\ConsultaGuardada;
use App\Models\ConsultaVersion;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Guarda y recupera consultas del explorador. Cada vez que se guarda una
 * especificación bajo una consulta, se crea una versión nueva (numeración
 * incremental), de modo que las consultas quedan reproducibles.
 */
class RepositorioConsultas
{
    public const REPORTE = 'pendientes_dependencia';

    /**
     * Crea una consulta guardada con su primera versión, a nombre del usuario.
     */
    public function crear(User $autor, string $nombre, ConsultaExplorador $consulta, string $visibilidad = 'privada'): ConsultaGuardada
    {
        return DB::transaction(function () use ($autor, $nombre, $consulta, $visibilidad) {
            $guardada = ConsultaGuardada::create([
                'creado_por' => $autor->id,
                'nombre' => $nombre,
                'reporte' => self::REPORTE,
                'visibilidad' => $visibilidad === 'institucional' ? 'institucional' : 'privada',
            ]);

            $this->agregarVersion($guardada, $consulta);

            return $guardada;
        });
    }

    /**
     * Añade una versión nueva a una consulta existente con el siguiente número.
     */
    public function agregarVersion(ConsultaGuardada $guardada, ConsultaExplorador $consulta): ConsultaVersion
    {
        $numero = (int) $guardada->versiones()->max('numero') + 1;

        return $guardada->versiones()->create([
            'numero' => $numero,
            'agrupaciones' => $consulta->agrupaciones,
            'filtros' => $consulta->filtros,
            'dependencia' => $consulta->dependencia,
            'orden' => $consulta->orden,
        ]);
    }

    /**
     * Consultas visibles para un usuario: las propias más las institucionales,
     * del reporte actual.
     *
     * @return Collection<int, ConsultaGuardada>
     */
    public function visiblesPara(User $usuario): Collection
    {
        return ConsultaGuardada::query()
            ->where('reporte', self::REPORTE)
            ->where(function ($query) use ($usuario) {
                $query->where('creado_por', $usuario->id)
                    ->orWhere('visibilidad', 'institucional');
            })
            ->orderByDesc('updated_at')
            ->get();
    }

    /** ¿Puede este usuario ver esta consulta? Propia o institucional. */
    public function puedeVer(User $usuario, ConsultaGuardada $guardada): bool
    {
        return $guardada->creado_por === $usuario->id || $guardada->esInstitucional();
    }
}
