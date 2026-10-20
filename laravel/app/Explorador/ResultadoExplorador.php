<?php

namespace App\Explorador;

use App\Models\Publicacion;
use Illuminate\Support\Collection;

/**
 * Resultado de ejecutar una consulta del explorador: las columnas de dimensión
 * (sus etiquetas), las filas (cada una con sus valores de dimensión ya
 * legibles y el conteo) y el total. Sin agrupaciones, columnas queda vacío y
 * hay una sola fila con el conteo del universo filtrado.
 */
class ResultadoExplorador
{
    /**
     * @param  array<int, string>  $columnas
     * @param  Collection<int, array{dimensiones: array<int, array{clave: string, etiqueta: string, valor: string}>, cantidad: int}>  $filas
     */
    public function __construct(
        public readonly Publicacion $publicacion,
        public readonly ConsultaExplorador $consulta,
        public readonly array $columnas,
        public readonly Collection $filas,
        public readonly int $total,
    ) {}

    public function tieneAgrupaciones(): bool
    {
        return $this->columnas !== [];
    }

    public function vacio(): bool
    {
        return $this->filas->isEmpty() || $this->total === 0;
    }

    /** Mayor conteo entre las filas, para escalar barras en la tabla. */
    public function maximo(): int
    {
        return max(1, (int) $this->filas->max('cantidad'));
    }
}
