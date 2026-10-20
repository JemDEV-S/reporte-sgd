<?php

namespace App\Reportes\PendientesDependencia;

use App\Models\Publicacion;
use Illuminate\Support\Str;

/**
 * Resultado del resumen jerárquico. Expone utilidades de navegación para el
 * drill-down (raíces, hijas de un nodo, ruta de migas) y los totales
 * institucionales, todos derivados del mismo conjunto de filas.
 */
class ResumenPendientes
{
    /**
     * @param  array<string, FilaPendientes>  $filas
     */
    public function __construct(
        public readonly Publicacion $publicacion,
        public readonly FiltrosPendientes $filtros,
        public readonly array $filas,
    ) {}

    public function fila(string $codigo): ?FilaPendientes
    {
        return $this->filas[$codigo] ?? null;
    }

    /** Busca en todo el catálogo autorizado; no altera el foco ni sus totales. */
    public function buscar(?string $texto): array
    {
        if ($texto === null || trim($texto) === '') {
            return $this->ordenar($this->filas);
        }

        $aguja = Str::lower(Str::ascii(trim($texto)));

        return $this->ordenar(array_filter($this->filas, fn (FilaPendientes $fila) => str_contains(Str::lower(Str::ascii($fila->codigo.' '.$fila->nombre)), $aguja)
        ));
    }

    /**
     * Dependencias raíz (sin padre), ordenadas por código.
     *
     * @return array<int, FilaPendientes>
     */
    public function raices(): array
    {
        $raices = array_filter($this->filas, fn (FilaPendientes $f) => $f->codigoPadre === null);

        return $this->ordenar($raices);
    }

    /**
     * Hijas directas de una dependencia, ordenadas por código.
     *
     * @return array<int, FilaPendientes>
     */
    public function hijasDe(string $codigo): array
    {
        $fila = $this->fila($codigo);
        if ($fila === null) {
            return [];
        }

        $hijas = array_map(fn (string $c) => $this->filas[$c], $fila->hijas);

        return $this->ordenar($hijas);
    }

    /**
     * Ruta de migas de pan desde la raíz hasta la dependencia indicada.
     *
     * @return array<int, FilaPendientes>
     */
    public function ruta(string $codigo): array
    {
        $ruta = [];
        $actual = $this->fila($codigo);
        while ($actual !== null) {
            array_unshift($ruta, $actual);
            $actual = $actual->codigoPadre !== null ? $this->fila($actual->codigoPadre) : null;
        }

        return $ruta;
    }

    /** Totales institucionales: suma de las cifras propias de todas las dependencias. */
    public function totales(): FilaPendientes
    {
        $total = new FilaPendientes(
            codigo: '',
            nombre: 'Total institucional',
            nivel: -1,
            activa: true,
            codigoPadre: null,
        );

        foreach ($this->filas as $fila) {
            $total->entradaNoLeidos += $fila->entradaNoLeidos;
            $total->entradaRecibidos += $fila->entradaRecibidos;
            $total->delegadosNoLeidos += $fila->delegadosNoLeidos;
            $total->delegadosRecibidos += $fila->delegadosRecibidos;
            $total->prioridadNormal += $fila->prioridadNormal;
            $total->prioridadUrgente += $fila->prioridadUrgente;
            $total->prioridadMuyUrgente += $fila->prioridadMuyUrgente;
        }

        return $total;
    }

    /**
     * @param  array<string, FilaPendientes>  $filas
     * @return array<int, FilaPendientes>
     */
    private function ordenar(array $filas): array
    {
        $lista = array_values($filas);
        usort($lista, fn (FilaPendientes $a, FilaPendientes $b) => strcmp($a->codigo, $b->codigo));

        return $lista;
    }
}
