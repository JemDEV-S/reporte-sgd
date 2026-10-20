<?php

namespace App\Reportes\PendientesDependencia;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Emite un CSV con el formato común de las exportaciones del reporte: BOM
 * UTF-8, separador ';' (Excel en configuración regional española), un bloque de
 * metadatos, una línea en blanco y la tabla. Recibe ya resueltos los metadatos,
 * columnas y filas; no conoce las reglas del reporte.
 *
 * Seguridad (inyección de fórmulas en CSV): asuntos y nombres son texto libre.
 * Una celda que empiece por '=', '+', '-', '@' o un carácter de control puede
 * interpretarse como fórmula al abrir el archivo en Excel/Sheets. Se neutraliza
 * anteponiendo un apóstrofo, sin alterar el valor almacenado en la base. No se
 * confía en las comillas del CSV como protección.
 */
class EscritorCsv
{
    /** Caracteres que, al inicio de una celda, pueden iniciar una fórmula. */
    private const DISPARADORES_FORMULA = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * @param  array<int, array<int, string>>  $metadatos  Pares [etiqueta, valor].
     * @param  array<int, string>  $columnas  Encabezados de la tabla.
     * @param  iterable<int, array<int, string|int>>  $filas  Filas de datos.
     * @param  array<int, string|int>|null  $filaTotales  Fila de totales opcional.
     */
    public function descargar(
        string $nombreArchivo,
        array $metadatos,
        array $columnas,
        iterable $filas,
        ?array $filaTotales = null,
    ): StreamedResponse {
        return response()->streamDownload(function () use ($metadatos, $columnas, $filas, $filaTotales) {
            $salida = fopen('php://output', 'wb');

            // BOM UTF-8 para que Excel (es-PE) muestre bien los acentos.
            fwrite($salida, "\xEF\xBB\xBF");

            foreach ($metadatos as $linea) {
                fputcsv($salida, array_map([self::class, 'neutralizar'], $linea), ';');
            }
            fputcsv($salida, [], ';'); // Separación entre metadatos y tabla.

            fputcsv($salida, array_map([self::class, 'neutralizar'], $columnas), ';');
            foreach ($filas as $fila) {
                fputcsv($salida, array_map([self::class, 'neutralizar'], $fila), ';');
            }
            if ($filaTotales !== null) {
                fputcsv($salida, array_map([self::class, 'neutralizar'], $filaTotales), ';');
            }

            fclose($salida);
        }, $nombreArchivo, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    /**
     * Antepone un apóstrofo a las celdas que podrían interpretarse como fórmula,
     * conservando el valor visible. Los no-textos (enteros) se dejan intactos.
     */
    private static function neutralizar(string|int|null $valor): string|int
    {
        if (! is_string($valor) || $valor === '') {
            return $valor ?? '';
        }

        return in_array($valor[0], self::DISPARADORES_FORMULA, true) ? "'".$valor : $valor;
    }
}
