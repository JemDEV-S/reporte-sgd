<?php

namespace App\Informes;

/**
 * Redactor determinista: compone los textos del informe (resumen general,
 * conclusiones y recomendaciones) con plantillas fijas y las cifras verificadas
 * de la evidencia. No hay IA ni dependencias externas; las mismas cifras
 * producen siempre el mismo texto, de modo que el informe es reproducible.
 *
 * Toda cifra proviene de EvidenciaInforme; esta clase no consulta la base de
 * datos ni calcula agregados.
 */
class RedactorPlantilla implements RedactorInforme
{
    public function clave(): string
    {
        return 'plantilla';
    }

    /**
     * Devuelve las piezas de texto del informe, listas para colocar en el
     * documento: 'resumen', 'conclusiones' (lista) y 'recomendaciones' (lista).
     *
     * @return array{resumen: string, conclusiones: array<int, string>, recomendaciones: array<int, string>}
     */
    public function redactar(EvidenciaInforme $evidencia): array
    {
        return [
            'resumen' => $this->resumen($evidencia),
            'conclusiones' => $this->conclusiones($evidencia),
            'recomendaciones' => $this->recomendaciones($evidencia),
        ];
    }

    private function resumen(EvidenciaInforme $evidencia): string
    {
        $r = $evidencia->valor('resumen');
        $total = (int) $r['total'];

        if ($total === 0) {
            return 'No se identificaron documentos pendientes de atención en las unidades orgánicas con las reglas del reporte.';
        }

        $pct = $r['pct_no_leidos'];

        return "Se identificó un total de {$this->n($total)} documentos pendientes de atención. ".
            "El {$pct}% no ha sido leído, con {$this->n((int) $r['sin_atender'])} recibidos sin atender ".
            "y {$this->n((int) $r['urgentes'])} urgentes.";
    }

    /** @return array<int, string> */
    private function conclusiones(EvidenciaInforme $evidencia): array
    {
        $r = $evidencia->valor('resumen');
        $total = (int) $r['total'];

        if ($total === 0) {
            return ['No se registran documentos pendientes de atención en el alcance evaluado.'];
        }

        $conSaldo = (int) $evidencia->valor('dependencias_con_saldo');
        $deTotal = (int) $evidencia->valor('dependencias_total');

        return [
            "Se identificó un total de {$this->n($total)} documentos pendientes en las unidades orgánicas de la Municipalidad.",
            "El {$r['pct_no_leidos']}% de documentos pendientes no ha sido leído por los responsables designados.",
            "Existen {$this->n((int) $r['sin_atender'])} documentos recibidos sin atención y {$this->n((int) $r['urgentes'])} documentos calificados como urgentes.",
            "Los pendientes se concentran en {$conSaldo} de {$deTotal} dependencias; las áreas con mayor saldo requieren atención prioritaria.",
            'Resulta necesario fortalecer los mecanismos de control y seguimiento documentario de la Municipalidad.',
        ];
    }

    /** @return array<int, string> */
    private function recomendaciones(EvidenciaInforme $evidencia): array
    {
        return [
            'Disponer la atención inmediata de los documentos urgentes pendientes.',
            'Fortalecer el uso obligatorio del SGD por parte de todos los servidores responsables.',
            'Priorizar la revisión de los documentos no leídos en las dependencias con mayor saldo.',
        ];
    }

    private function n(int $valor): string
    {
        return number_format($valor);
    }
}
