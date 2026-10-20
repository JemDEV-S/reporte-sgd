<?php

namespace App\Informes;

use App\Models\Informe;
use Illuminate\Support\Carbon;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Style\Language;
use PhpOffice\PhpWord\Writer\Word2007 as Word2007Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Arma el documento Word (.docx) de un informe situacional de pendientes, con
 * la estructura del informe de referencia: encabezado (A/DE/ASUNTO/FECHA),
 * resumen general y su cuadro por nivel, ranking, cuadros por gerencia/oficina,
 * conclusiones, recomendaciones y firma.
 *
 * Todas las cifras provienen de la evidencia verificada guardada en el informe;
 * esta clase solo las dispone en el documento, no recalcula nada.
 */
class DocumentoWord
{
    private const AZUL = '1F3864';

    // Tamaños de fuente de las tablas (en puntos). Reducidos para que las
    // tablas anchas no se desborden del ancho de página.
    private const FUENTE_TABLA = 8;

    private const FUENTE_NOMBRE = 8;

    public function descargar(Informe $informe): StreamedResponse
    {
        $libro = $this->construir($informe);
        $nombre = $this->nombreArchivo($informe);

        return response()->streamDownload(function () use ($libro) {
            $escritor = new Word2007Writer($libro);
            $escritor->save('php://output');
        }, $nombre, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    private function construir(Informe $informe): PhpWord
    {
        $datos = $informe->evidencia;
        $textos = $datos['textos'];

        $word = new PhpWord;
        $word->getSettings()->setThemeFontLang(new Language('es-PE'));
        $word->setDefaultFontName('Arial');
        $word->setDefaultFontSize(10);

        $this->estilos($word);

        $seccion = $word->addSection(['marginTop' => 1200, 'marginBottom' => 1000, 'marginLeft' => 1200, 'marginRight' => 1200]);

        $this->encabezado($seccion, $informe);
        $this->resumenGeneral($seccion, $datos, $textos);
        $this->ranking($seccion, $datos);
        $this->listas($seccion, $textos);
        $this->firma($seccion, $informe);

        return $word;
    }

    private function estilos(PhpWord $word): void
    {
        $word->addTitleStyle(1, ['bold' => true, 'size' => 11, 'color' => self::AZUL], ['spaceBefore' => 240, 'spaceAfter' => 120]);
        $word->addParagraphStyle('cuerpo', ['align' => Jc::BOTH, 'spaceAfter' => 120]);
    }

    private function encabezado(Section $s, Informe $informe): void
    {
        $s->addText('MUNICIPALIDAD DISTRITAL DE SAN JERÓNIMO', ['bold' => true, 'size' => 12, 'color' => self::AZUL], ['align' => Jc::CENTER]);
        $s->addText('OFICINA DE TECNOLOGÍAS DE LA INFORMACIÓN', ['bold' => true, 'size' => 10], ['align' => Jc::CENTER]);
        $s->addText('"Año de la Esperanza y el Fortalecimiento de la Democracia"', ['italic' => true, 'size' => 9], ['align' => Jc::CENTER, 'spaceAfter' => 200]);

        $fecha = Carbon::now()->translatedFormat('d \d\e F \d\e Y');
        $numero = $informe->numero ?: 'N° __________-'.Carbon::now()->year.'-MDSJ/OTI';
        $s->addText('Cusco, '.$fecha, ['size' => 10], ['spaceAfter' => 60]);
        $s->addText('INFORME '.$numero, ['bold' => true, 'size' => 12, 'color' => self::AZUL], ['spaceAfter' => 200]);

        // Bloque A / DE / ASUNTO / FECHA en una tabla sin bordes.
        $tabla = $s->addTable(['cellMargin' => 40]);
        $filas = [
            ['A', $informe->destinatario, $informe->destinatario_cargo],
            ['DE', $informe->remitente, $informe->remitente_cargo],
        ];
        foreach ($filas as [$etq, $nombre, $cargo]) {
            $tabla->addRow();
            $tabla->addCell(1200)->addText($etq, ['bold' => true]);
            $tabla->addCell(500)->addText(':');
            $celda = $tabla->addCell(7800);
            $celda->addText(strtoupper((string) ($nombre ?: '__________________')), ['bold' => true]);
            if ($cargo) {
                $celda->addText($cargo);
            }
        }
        $tabla->addRow();
        $tabla->addCell(1200)->addText('ASUNTO', ['bold' => true]);
        $tabla->addCell(500)->addText(':');
        $tabla->addCell(7800)->addText($informe->asunto ?: 'REPORTE SITUACIONAL DE DOCUMENTOS PENDIENTES EN EL SISTEMA DE GESTIÓN DOCUMENTAL (SGD)');
        $tabla->addRow();
        $tabla->addCell(1200)->addText('FECHA', ['bold' => true]);
        $tabla->addCell(500)->addText(':');
        $tabla->addCell(7800)->addText('Cusco, '.$fecha);

        $s->addTextBreak(1);
        $s->addText(
            'Previo un cordial saludo, tengo el agrado de dirigirme a usted en cumplimiento de las acciones de '.
            'seguimiento y control del Sistema de Gestión Documental (SGD), para informar sobre el estado situacional '.
            'de los documentos pendientes de atención registrados en las unidades orgánicas de la Municipalidad.',
            null, 'cuerpo'
        );
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  array{resumen: string, conclusiones: array<int, string>, recomendaciones: array<int, string>}  $textos
     */
    private function resumenGeneral(Section $s, array $datos, array $textos): void
    {
        $s->addTitle('RESUMEN GENERAL', 1);
        $s->addText($textos['resumen'], null, 'cuerpo');

        $tabla = $this->tablaConCabecera($s, ['Nivel', 'Total Pendientes', 'No Leídos', '% No Leídos', 'Sin Atender', 'Urgentes']);
        foreach ($datos['por_nivel'] as $fila) {
            $this->filaNivel($tabla, $fila);
        }
        $this->filaNivel($tabla, $datos['resumen'], destacar: true);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function ranking(Section $s, array $datos): void
    {
        $s->addTitle('RANKING ORDENADO DE MAYOR A MENOR POR TOTAL PENDIENTES', 1);

        // Anchos de las 8 columnas de datos: #, Unidad, Total, y el desglose de
        // Entrada (no leído/sin atender) y Delegados (no leído/sin atender),
        // más Urgentes.
        $a = ['num' => 400, 'unidad' => 3700, 'total' => 850, 'celda' => 950, 'urg' => 850];

        $tabla = $s->addTable([
            'borderSize' => 4, 'borderColor' => 'BFBFBF', 'cellMargin' => 30, 'width' => 100 * 50, 'unit' => 'pct',
        ]);

        $this->cabeceraRanking($tabla, $a);

        // Escala de color del Total: rojo = mayor backlog, verde = menor.
        $maximo = max(1, (int) (collect($datos['ranking'])->max('total') ?? 1));

        foreach ($datos['ranking'] as $i => $fila) {
            $tabla->addRow();
            $this->celdaNum($tabla, (string) ($i + 1), $a['num']);
            $tabla->addCell($a['unidad'])->addText($fila['nombre'], ['size' => self::FUENTE_NOMBRE]);
            $this->celdaNum($tabla, $this->n($fila['entrada_no_leidos'] ?? 0), $a['celda']);
            $this->celdaNum($tabla, $this->n($fila['entrada_sin_atender'] ?? 0), $a['celda']);
            $this->celdaNum($tabla, $this->n($fila['delegados_no_leidos'] ?? 0), $a['celda']);
            $this->celdaNum($tabla, $this->n($fila['delegados_sin_atender'] ?? 0), $a['celda']);
            // Total con escala rojo→verde, antes de Urgentes; el número queda visible.
            $this->celdaTotal($tabla, (int) $fila['total'], $maximo, $a['total']);
            $this->celdaNum($tabla, $this->n($fila['urgentes']), $a['urg']);
        }

        // Total general.
        $r = $datos['resumen'];
        $tabla->addRow();
        $this->celdaNum($tabla, '', $a['num'], fondo: self::AZUL);
        $celda = $tabla->addCell($a['unidad'], ['bgColor' => self::AZUL]);
        $celda->addText('TOTAL GENERAL', ['bold' => true, 'color' => 'FFFFFF', 'size' => self::FUENTE_TABLA]);
        $this->celdaNum($tabla, $this->n($r['entrada_no_leidos'] ?? 0), $a['celda'], negrita: true, fondo: self::AZUL, claro: true);
        $this->celdaNum($tabla, $this->n($r['entrada_sin_atender'] ?? 0), $a['celda'], negrita: true, fondo: self::AZUL, claro: true);
        $this->celdaNum($tabla, $this->n($r['delegados_no_leidos'] ?? 0), $a['celda'], negrita: true, fondo: self::AZUL, claro: true);
        $this->celdaNum($tabla, $this->n($r['delegados_sin_atender'] ?? 0), $a['celda'], negrita: true, fondo: self::AZUL, claro: true);
        $this->celdaNum($tabla, $this->n($r['total']), $a['total'], negrita: true, fondo: self::AZUL, claro: true);
        $this->celdaNum($tabla, $this->n($r['urgentes']), $a['urg'], negrita: true, fondo: self::AZUL, claro: true);
    }

    /**
     * Cabecera de dos filas del ranking: ENTRADA y DELEGADOS como grupos (cada
     * uno con 'No leído' y 'Recibidos sin atender'), usando combinación de
     * celdas. Las columnas #, Unidad, Total y Urgentes abarcan las dos filas
     * (vMerge). El Total va tras los grupos de bandeja, antes de Urgentes.
     *
     * @param  array<string, int>  $a  anchos de columna
     */
    private function cabeceraRanking(Table $tabla, array $a): void
    {
        $fondo = ['bgColor' => self::AZUL];
        $fuente = ['bold' => true, 'color' => 'FFFFFF', 'size' => self::FUENTE_TABLA];
        $centro = ['align' => Jc::CENTER];

        // Fila 1: celdas simples con vMerge 'restart' para las que ocupan dos
        // filas, y celdas con gridSpan=2 para los grupos de bandeja.
        $tabla->addRow();
        $tabla->addCell($a['num'], $fondo + ['vMerge' => 'restart'])->addText('#', $fuente, $centro);
        $tabla->addCell($a['unidad'], $fondo + ['vMerge' => 'restart'])->addText('Unidad Orgánica', $fuente, $centro);
        $tabla->addCell($a['celda'] * 2, $fondo + ['gridSpan' => 2])->addText('ENTRADA', $fuente, $centro);
        $tabla->addCell($a['celda'] * 2, $fondo + ['gridSpan' => 2])->addText('DELEGADOS', $fuente, $centro);
        $tabla->addCell($a['total'], $fondo + ['vMerge' => 'restart'])->addText('Total', $fuente, $centro);
        $tabla->addCell($a['urg'], $fondo + ['vMerge' => 'restart'])->addText('Urgentes', $fuente, $centro);

        // Fila 2: las subcolumnas de cada grupo y las continuaciones vMerge (vacías).
        $tabla->addRow();
        $tabla->addCell($a['num'], $fondo + ['vMerge' => 'continue']);
        $tabla->addCell($a['unidad'], $fondo + ['vMerge' => 'continue']);
        $tabla->addCell($a['celda'], $fondo)->addText('No leído', $fuente, $centro);
        $tabla->addCell($a['celda'], $fondo)->addText('Recibidos sin atender', $fuente, $centro);
        $tabla->addCell($a['celda'], $fondo)->addText('No leído', $fuente, $centro);
        $tabla->addCell($a['celda'], $fondo)->addText('Recibidos sin atender', $fuente, $centro);
        $tabla->addCell($a['total'], $fondo + ['vMerge' => 'continue']);
        $tabla->addCell($a['urg'], $fondo + ['vMerge' => 'continue']);
    }

    /**
     * @param  array{resumen: string, conclusiones: array<int, string>, recomendaciones: array<int, string>}  $textos
     */
    private function listas(Section $s, array $textos): void
    {
        $s->addTitle('CONCLUSIONES', 1);
        foreach ($textos['conclusiones'] as $c) {
            $s->addListItem($c, 0, null, null, 'cuerpo');
        }

        $s->addTitle('RECOMENDACIONES', 1);
        foreach ($textos['recomendaciones'] as $r) {
            $s->addListItem($r, 0, null, null, 'cuerpo');
        }
    }

    private function firma(Section $s, Informe $informe): void
    {
        $s->addTextBreak(1);
        $s->addText('Es todo cuanto informo para conocimiento y fines correspondientes.', null, 'cuerpo');
        $s->addText('Atentamente,', ['bold' => true], ['spaceAfter' => 600]);

        $s->addText('Documento firmado digitalmente', ['size' => 9], ['align' => Jc::CENTER]);
        $s->addText(strtoupper((string) ($informe->remitente ?: '__________________')), ['bold' => true], ['align' => Jc::CENTER]);
        if ($informe->remitente_cargo) {
            $s->addText($informe->remitente_cargo, ['size' => 9], ['align' => Jc::CENTER]);
        }
        $s->addText('OFICINA DE TECNOLOGÍAS DE LA INFORMACIÓN', ['size' => 9], ['align' => Jc::CENTER]);
    }

    // --- Utilidades de tabla ---

    /** @param array<int, string> $cabeceras */
    private function tablaConCabecera(Section $s, array $cabeceras): Table
    {
        $tabla = $s->addTable([
            'borderSize' => 4, 'borderColor' => 'BFBFBF', 'cellMargin' => 40, 'width' => 100 * 50, 'unit' => 'pct',
        ]);
        $tabla->addRow();
        $anchos = $this->anchos(count($cabeceras));
        foreach ($cabeceras as $i => $texto) {
            $celda = $tabla->addCell($anchos[$i], ['bgColor' => self::AZUL]);
            $celda->addText($texto, ['bold' => true, 'color' => 'FFFFFF', 'size' => self::FUENTE_TABLA], ['align' => Jc::CENTER]);
        }

        return $tabla;
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private function filaNivel(Table $tabla, array $fila, bool $destacar = false): void
    {
        $fondo = $destacar ? self::AZUL : null;
        $color = $destacar ? 'FFFFFF' : null;
        $bold = $destacar;
        $tabla->addRow();
        $celda = $tabla->addCell(3200, $fondo ? ['bgColor' => $fondo] : []);
        $celda->addText($fila['nombre'], array_filter(['bold' => $bold, 'color' => $color, 'size' => self::FUENTE_NOMBRE]));
        $this->celdaNum($tabla, $this->n($fila['total']), 1600, negrita: $bold, fondo: $fondo, claro: $destacar);
        $this->celdaNum($tabla, $this->n($fila['no_leidos']), 1400, negrita: $bold, fondo: $fondo, claro: $destacar);
        $this->celdaNum($tabla, $this->pct($fila['pct_no_leidos']), 1400, negrita: $bold, fondo: $fondo, claro: $destacar);
        $this->celdaNum($tabla, $this->n($fila['sin_atender']), 1400, negrita: $bold, fondo: $fondo, claro: $destacar);
        $this->celdaNum($tabla, $this->n($fila['urgentes']), 1200, negrita: $bold, fondo: $fondo, claro: $destacar);
    }

    private function celdaNum(
        Table $tabla,
        string $texto,
        int $ancho,
        bool $negrita = false,
        ?string $fondo = null,
        bool $claro = false,
    ): void {
        $celda = $tabla->addCell($ancho, $fondo ? ['bgColor' => $fondo] : []);
        $fuente = array_filter([
            'bold' => $negrita,
            'size' => self::FUENTE_TABLA,
            'color' => $claro ? 'FFFFFF' : null,
        ]);
        $celda->addText($texto, $fuente, ['align' => Jc::CENTER]);
    }

    /**
     * Celda del Total con fondo en escala rojo→verde según su magnitud relativa
     * al mayor total del ranking. El número se imprime siempre, de modo que el
     * color refuerza la lectura pero nunca es el único canal (accesible y legible
     * en impresión o con daltonismo).
     */
    private function celdaTotal(Table $tabla, int $total, int $maximo, int $ancho): void
    {
        [$fondo, $textoBlanco] = $this->escalaRojoVerde($total, $maximo);
        $celda = $tabla->addCell($ancho, ['bgColor' => $fondo]);
        $celda->addText($this->n($total), [
            'bold' => true,
            'size' => self::FUENTE_TABLA,
            'color' => $textoBlanco ? 'FFFFFF' : '1A1A1A',
        ], ['align' => Jc::CENTER]);
    }

    /**
     * Interpola un color de rojo (mayor backlog) a verde (menor) según la razón
     * total/máximo. Devuelve [hexFondo, usarTextoBlanco]: el texto va en blanco
     * sobre los extremos saturados y en oscuro sobre los tonos claros del centro.
     *
     * @return array{0: string, 1: bool}
     */
    private function escalaRojoVerde(int $total, int $maximo): array
    {
        $t = $maximo > 0 ? max(0.0, min(1.0, $total / $maximo)) : 0.0;

        // Extremos ligeramente desaturados para que el texto contraste bien.
        // Rojo (t=1): C0392B; amarillo medio (t=0.5): F1C40F; verde (t=0): 1E8449.
        if ($t >= 0.5) {
            $f = ($t - 0.5) / 0.5; // 0 en el medio → 1 en el rojo
            $r = $this->lerp(0xF1, 0xC0, $f);
            $g = $this->lerp(0xC4, 0x39, $f);
            $b = $this->lerp(0x0F, 0x2B, $f);
        } else {
            $f = $t / 0.5; // 0 en el verde → 1 en el medio
            $r = $this->lerp(0x1E, 0xF1, $f);
            $g = $this->lerp(0x84, 0xC4, $f);
            $b = $this->lerp(0x49, 0x0F, $f);
        }

        $hex = sprintf('%02X%02X%02X', $r, $g, $b);
        // Texto blanco sobre fondos oscuros (luminancia baja); oscuro sobre los claros.
        $luminancia = 0.299 * $r + 0.587 * $g + 0.114 * $b;

        return [$hex, $luminancia < 150];
    }

    private function lerp(int $desde, int $hasta, float $f): int
    {
        return (int) round($desde + ($hasta - $desde) * $f);
    }

    /** @return array<int, int> */
    private function anchos(int $columnas): array
    {
        // Anchos que deben coincidir con los de las celdas del cuerpo.
        return match ($columnas) {
            // Resumen por nivel / cuadros por grupo.
            6 => [3200, 1600, 1400, 1400, 1400, 1200],
            // Ranking: #, nombre, Total, Entrada, Delegados, No Leídos, Sin Atender, Urgentes.
            8 => [450, 4200, 950, 900, 950, 950, 950, 900],
            default => array_fill(0, $columnas, (int) (10000 / $columnas)),
        };
    }

    private function n(int|float $valor): string
    {
        return number_format((int) $valor);
    }

    private function pct(int|float $valor): string
    {
        return number_format((float) $valor, 1).'%';
    }

    private function nombreArchivo(Informe $informe): string
    {
        $base = 'informe_pendientes_pub'.$informe->publicacion_id;

        return $base.'.docx';
    }
}
