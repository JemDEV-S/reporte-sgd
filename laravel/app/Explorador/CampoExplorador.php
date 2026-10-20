<?php

namespace App\Explorador;

/**
 * Un campo permitido del explorador: su clave estable (la que se guarda en la
 * consulta), su etiqueta legible, la expresión SQL segura que lo calcula y, si
 * es categórico, las opciones admitidas para filtrarlo.
 *
 * El conjunto de campos es una lista cerrada (ver CatalogoExplorador): el
 * usuario nunca escribe SQL. La consulta guardada solo referencia estas claves,
 * de modo que validarla es comprobar que cada clave existe en el catálogo.
 */
class CampoExplorador
{
    /**
     * @param  string  $expresion  Expresión SQL segura (sin entrada de usuario) que
     *                             produce el valor; se usa tanto para agrupar como
     *                             para seleccionar. No contiene parámetros libres.
     * @param  array<string, string>|null  $opciones  Para campos categóricos: valor => etiqueta.
     *                                                null = campo no filtrable por lista cerrada.
     */
    public function __construct(
        public readonly string $clave,
        public readonly string $etiqueta,
        public readonly string $expresion,
        public readonly bool $agrupable = true,
        public readonly bool $filtrable = true,
        public readonly ?array $opciones = null,
    ) {}

    /** Etiqueta legible de un valor categórico; el propio valor si no hay mapa. */
    public function etiquetaValor(?string $valor): string
    {
        if ($valor === null || $valor === '') {
            return '—';
        }

        return $this->opciones[$valor] ?? $valor;
    }
}
