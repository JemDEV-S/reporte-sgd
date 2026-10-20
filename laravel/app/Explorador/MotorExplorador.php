<?php

namespace App\Explorador;

use App\Models\DestinoCaptura;
use App\Models\Publicacion;
use App\Reportes\PendientesDependencia\FiltrosPendientes;
use App\Reportes\PendientesDependencia\MotorPendientesDependencia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Ejecuta una ConsultaExplorador sobre los destinos pendientes de la
 * publicación vigente. Reutiliza la MISMA consulta base del reporte
 * (MotorPendientesDependencia::consultaBase()): el universo del explorador es
 * exactamente el de pendientes —tipo '01', activo, recepción {0,1}— de modo que
 * sus cifras son consistentes con el reporte y su exportación.
 *
 * La medida es siempre el conteo de destinos (asignaciones). Todas las
 * expresiones SQL provienen del catálogo (lista cerrada); los valores de filtro
 * se enlazan como parámetros. No hay SQL libre del usuario.
 */
class MotorExplorador
{
    public function __construct(
        private readonly Publicacion $publicacion,
    ) {}

    public function ejecutar(ConsultaExplorador $consulta): ResultadoExplorador
    {
        $query = $this->base($consulta);

        return $consulta->tieneAgrupaciones()
            ? $this->ejecutarAgrupado($query, $consulta)
            : $this->ejecutarTotal($query, $consulta);
    }

    /**
     * Consulta base: el universo de pendientes del reporte más los filtros del
     * explorador (lista cerrada y, si se indica, dependencia exacta).
     *
     * @return Builder<DestinoCaptura>
     */
    private function base(ConsultaExplorador $consulta): Builder
    {
        // El alcance por dependencia del reporte se expresa aquí; la dependencia
        // concreta del explorador se añade como filtro exacto sobre esa base.
        $filtrosReporte = new FiltrosPendientes(
            dependenciasAutorizadas: $consulta->dependencia !== null ? [$consulta->dependencia] : null,
        );
        $query = (new MotorPendientesDependencia($this->publicacion, $filtrosReporte))->consultaBase();

        foreach ($consulta->filtros as $clave => $valor) {
            $campo = CatalogoExplorador::campo($clave);
            if ($campo === null) {
                continue;
            }
            // La expresión viene del catálogo (sin entrada de usuario); el valor
            // se enlaza como parámetro.
            $query->whereRaw("{$campo->expresion} = ?", [$valor]);
        }

        return $query;
    }

    /**
     * @param  Builder<DestinoCaptura>  $query
     */
    private function ejecutarAgrupado(Builder $query, ConsultaExplorador $consulta): ResultadoExplorador
    {
        $campos = array_map(
            fn (string $clave) => CatalogoExplorador::campo($clave),
            $consulta->agrupaciones,
        );

        foreach ($campos as $i => $campo) {
            $alias = "dim_{$i}";
            $query->selectRaw("{$campo->expresion} as {$alias}");
            $query->groupByRaw($campo->expresion);
        }
        $query->selectRaw('count(*) as cantidad');

        $this->ordenar($query, $consulta, count($campos));

        $filas = $query->get()->map(function ($fila) use ($campos) {
            $dimensiones = [];
            foreach ($campos as $i => $campo) {
                $valor = $fila->{"dim_{$i}"};
                $dimensiones[] = [
                    'clave' => $campo->clave,
                    'etiqueta' => $campo->etiqueta,
                    'valor' => $campo->etiquetaValor($valor === null ? null : (string) $valor),
                ];
            }

            return [
                'dimensiones' => $dimensiones,
                'cantidad' => (int) $fila->cantidad,
            ];
        });

        return new ResultadoExplorador(
            publicacion: $this->publicacion,
            consulta: $consulta,
            columnas: array_map(fn (CampoExplorador $c) => $c->etiqueta, $campos),
            filas: $filas,
            total: $filas->sum('cantidad'),
        );
    }

    /**
     * Sin agrupaciones: una sola fila con el conteo total del universo filtrado.
     *
     * @param  Builder<DestinoCaptura>  $query
     */
    private function ejecutarTotal(Builder $query, ConsultaExplorador $consulta): ResultadoExplorador
    {
        $total = $query->count();

        return new ResultadoExplorador(
            publicacion: $this->publicacion,
            consulta: $consulta,
            columnas: [],
            filas: new Collection([['dimensiones' => [], 'cantidad' => $total]]),
            total: $total,
        );
    }

    /**
     * @param  Builder<DestinoCaptura>  $query
     */
    private function ordenar(Builder $query, ConsultaExplorador $consulta, int $numDimensiones): void
    {
        match ($consulta->orden) {
            'cantidad_asc' => $query->orderBy('cantidad'),
            'etiqueta' => $this->ordenarPorDimensiones($query, $numDimensiones),
            default => $query->orderByDesc('cantidad'),
        };
    }

    /**
     * @param  Builder<DestinoCaptura>  $query
     */
    private function ordenarPorDimensiones(Builder $query, int $numDimensiones): void
    {
        for ($i = 0; $i < $numDimensiones; $i++) {
            $query->orderBy("dim_{$i}");
        }
    }
}
