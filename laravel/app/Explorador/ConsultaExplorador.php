<?php

namespace App\Explorador;

/**
 * Especificación declarativa de una consulta del explorador. Es exactamente lo
 * que se guarda en consulta_versiones (seleccion/filtros/agrupaciones/orden):
 * solo referencias a claves del catálogo y valores de listas cerradas, nunca
 * SQL. Se construye siempre a través de desde(), que descarta cualquier clave o
 * valor que no pertenezca al catálogo.
 */
class ConsultaExplorador
{
    /**
     * @param  array<int, string>  $agrupaciones  Claves de dimensión por las que agrupar (en orden).
     * @param  array<string, string>  $filtros  Clave de campo filtrable => valor de su lista cerrada.
     * @param  ?string  $dependencia  Código de dependencia exacto, o null para todas.
     * @param  string  $orden  'cantidad_desc' | 'cantidad_asc' | 'etiqueta'.
     */
    public function __construct(
        public readonly array $agrupaciones = [],
        public readonly array $filtros = [],
        public readonly ?string $dependencia = null,
        public readonly string $orden = 'cantidad_desc',
    ) {}

    /**
     * Construye una consulta validada contra el catálogo. Toda clave de
     * agrupación desconocida, filtro no permitido o valor fuera de la lista
     * cerrada se descarta silenciosamente: la consulta resultante siempre es
     * ejecutable y segura.
     *
     * @param  array<string, mixed>  $datos  Entrada cruda (p. ej. del request o de la persistencia).
     */
    public static function desde(array $datos): self
    {
        $agrupablesValidas = CatalogoExplorador::clavesAgrupables();
        $agrupaciones = array_values(array_filter(
            array_map('strval', (array) ($datos['agrupaciones'] ?? [])),
            fn (string $clave) => in_array($clave, $agrupablesValidas, true),
        ));
        // Sin duplicados, conservando el orden de aparición.
        $agrupaciones = array_values(array_unique($agrupaciones));

        $filtros = [];
        foreach ((array) ($datos['filtros'] ?? []) as $clave => $valor) {
            $campo = CatalogoExplorador::campo((string) $clave);
            if ($campo === null || ! $campo->filtrable || $campo->opciones === null) {
                continue;
            }
            $valor = (string) $valor;
            if ($valor !== '' && array_key_exists($valor, $campo->opciones)) {
                $filtros[(string) $clave] = $valor;
            }
        }

        $dependencia = isset($datos['dependencia']) && is_string($datos['dependencia']) && $datos['dependencia'] !== ''
            ? $datos['dependencia']
            : null;

        $orden = in_array($datos['orden'] ?? null, ['cantidad_desc', 'cantidad_asc', 'etiqueta'], true)
            ? $datos['orden']
            : 'cantidad_desc';

        return new self($agrupaciones, $filtros, $dependencia, $orden);
    }

    public function tieneAgrupaciones(): bool
    {
        return $this->agrupaciones !== [];
    }

    /** @return array<string, mixed> Forma serializable para guardar la consulta. */
    public function aArray(): array
    {
        return [
            'agrupaciones' => $this->agrupaciones,
            'filtros' => $this->filtros,
            'dependencia' => $this->dependencia,
            'orden' => $this->orden,
        ];
    }
}
