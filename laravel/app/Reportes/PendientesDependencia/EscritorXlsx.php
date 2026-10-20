<?php

namespace App\Reportes\PendientesDependencia;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Escribe una hoja de cálculo .xlsx con el formato común de las exportaciones
 * del reporte: un bloque de metadatos arriba, una fila de encabezados en
 * negrita y luego los datos. No conoce las reglas del reporte; recibe ya
 * resueltos los metadatos, columnas y filas desde una clase de exportación.
 */
class EscritorXlsx
{
    private const AZUL_ENCABEZADO = 'FF1D4ED8';

    private const GRIS_META = 'FFF1F5F9';

    /**
     * @param  array<int, array<int, string>>  $metadatos  Pares [etiqueta, valor].
     * @param  array<int, string>  $columnas  Encabezados de la tabla.
     * @param  iterable<int, array<int, string|int>>  $filas  Filas de datos.
     * @param  array<int, string|int>|null  $filaTotales  Fila de totales opcional, resaltada.
     */
    public function descargar(
        string $nombreArchivo,
        string $tituloHoja,
        array $metadatos,
        array $columnas,
        iterable $filas,
        ?array $filaTotales = null,
    ): StreamedResponse {
        $libro = new Spreadsheet;
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle($this->tituloSeguro($tituloHoja));

        $numColumnas = max(count($columnas), 2);
        $ultimaCol = Coordinate::stringFromColumnIndex($numColumnas);

        $fila = $this->escribirMetadatos($hoja, $metadatos);
        $fila++; // Línea en blanco entre metadatos y la tabla.

        $filaEncabezado = $fila;
        $this->escribirEncabezado($hoja, $columnas, $filaEncabezado);
        $fila++;

        foreach ($filas as $datos) {
            $this->escribirFila($hoja, $datos, $fila);
            $fila++;
        }

        if ($filaTotales !== null) {
            $this->escribirFila($hoja, $filaTotales, $fila);
            $rango = "A{$fila}:{$ultimaCol}{$fila}";
            $hoja->getStyle($rango)->getFont()->setBold(true);
            $hoja->getStyle($rango)->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::GRIS_META);
        }

        for ($i = 1; $i <= $numColumnas; $i++) {
            $hoja->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }
        $hoja->freezePane("A{$filaEncabezado}");

        return $this->respuesta($libro, $nombreArchivo);
    }

    /**
     * @param  array<int, array<int, string>>  $metadatos
     * @return int Primera fila libre tras los metadatos.
     */
    private function escribirMetadatos(Worksheet $hoja, array $metadatos): int
    {
        $fila = 1;
        foreach ($metadatos as [$etiqueta, $valor]) {
            $hoja->setCellValue("A{$fila}", $etiqueta);
            $hoja->setCellValueExplicit("B{$fila}", $valor, DataType::TYPE_STRING);
            $hoja->getStyle("A{$fila}")->getFont()->setBold(true);
            $hoja->getStyle("A{$fila}")->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::GRIS_META);
            $fila++;
        }

        return $fila;
    }

    /**
     * @param  array<int, string>  $columnas
     */
    private function escribirEncabezado(Worksheet $hoja, array $columnas, int $fila): void
    {
        $col = 1;
        foreach ($columnas as $titulo) {
            $hoja->setCellValue(Coordinate::stringFromColumnIndex($col).$fila, $titulo);
            $col++;
        }

        $ultimaCol = Coordinate::stringFromColumnIndex(max(count($columnas), 1));
        $estilo = $hoja->getStyle("A{$fila}:{$ultimaCol}{$fila}");
        $estilo->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $estilo->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::AZUL_ENCABEZADO);
        $estilo->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $estilo->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN);
    }

    /**
     * @param  array<int, string|int>  $datos
     */
    private function escribirFila(Worksheet $hoja, array $datos, int $fila): void
    {
        $col = 1;
        foreach ($datos as $valor) {
            $celda = Coordinate::stringFromColumnIndex($col).$fila;
            if (is_int($valor)) {
                $hoja->setCellValue($celda, $valor);
            } else {
                // Texto explícito para conservar ceros iniciales de los códigos.
                $hoja->setCellValueExplicit($celda, (string) $valor, DataType::TYPE_STRING);
            }
            $col++;
        }
    }

    private function respuesta(Spreadsheet $libro, string $nombreArchivo): StreamedResponse
    {
        $escritor = new XlsxWriter($libro);

        return response()->streamDownload(function () use ($escritor, $libro) {
            $escritor->save('php://output');
            $libro->disconnectWorksheets();
        }, $nombreArchivo, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    private function tituloSeguro(string $titulo): string
    {
        // Excel limita los títulos de hoja a 31 caracteres y prohíbe algunos.
        $limpio = preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/', ' ', $titulo) ?? 'Hoja';

        return mb_substr(trim($limpio), 0, 31) ?: 'Hoja';
    }
}
