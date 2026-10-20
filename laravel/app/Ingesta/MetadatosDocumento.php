<?php

namespace App\Ingesta;

/**
 * Resuelve, a partir de los campos crudos de un remito del SGD, los metadatos
 * documentales legibles de una cabecera: número documental visible y si está
 * incompleto (§4.2). Centralizar aquí el formato evita reconstruirlo de forma
 * distinta en cada punto; el origen (descripción) se resuelve en SQL por su
 * coste y se pasa ya resuelto.
 *
 * Fuente del número (verificada en §10): para TI_EMI 01/05,
 * NU_DOC_EMI-NU_ANN-DE_DOC_SIG; para los demás, DE_DOC_SIG. El valor
 * autoritativo es TDTX_REMITOS_RESUMEN.NU_DOC cuando está disponible (coincide
 * con la reconstrucción); la reconstrucción es el respaldo.
 */
class MetadatosDocumento
{
    /** Tipos de emisor cuyo número se compone con año y sigla. */
    private const TIPOS_COMPUESTOS = ['01', '05'];

    /**
     * Número documental visible. Prefiere el NU_DOC del resumen (autoritativo);
     * si falta, reconstruye con los componentes disponibles sin guiones vacíos.
     */
    public static function numero(
        ?string $tipoEmisor,
        ?string $numeroEmisor,
        ?string $anio,
        ?string $sigla,
        ?string $numeroResumen = null,
    ): ?string {
        if (self::limpio($numeroResumen) !== null) {
            return self::limpio($numeroResumen);
        }

        $tipo = self::limpio($tipoEmisor);
        if ($tipo !== null && in_array($tipo, self::TIPOS_COMPUESTOS, true)) {
            $partes = array_filter(
                [self::limpio($numeroEmisor), self::limpio($anio), self::limpio($sigla)],
                fn ($v) => $v !== null,
            );

            return $partes === [] ? null : implode('-', $partes);
        }

        return self::limpio($sigla);
    }

    /**
     * ¿El número está incompleto? Solo aplica a los tipos compuestos (01/05):
     * se marca cuando falta alguno de sus tres componentes. Para otros tipos,
     * la ausencia de sigla se refleja como "sin número", no como incompleto.
     */
    public static function incompleto(
        ?string $tipoEmisor,
        ?string $numeroEmisor,
        ?string $anio,
        ?string $sigla,
    ): bool {
        $tipo = self::limpio($tipoEmisor);
        if ($tipo === null || ! in_array($tipo, self::TIPOS_COMPUESTOS, true)) {
            return false;
        }

        return self::limpio($numeroEmisor) === null
            || self::limpio($anio) === null
            || self::limpio($sigla) === null;
    }

    private static function limpio(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }
        $texto = trim($valor);

        return $texto === '' ? null : $texto;
    }
}
