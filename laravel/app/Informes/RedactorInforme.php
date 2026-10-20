<?php

namespace App\Informes;

/**
 * Redacta la narrativa de un informe a partir de evidencia ya verificada. El
 * redactor FORMATEA las cifras que recibe; no las calcula ni las inventa.
 *
 * Es el punto de extensión para la IA: una implementación futura basada en
 * Claude recibiría la misma EvidenciaInforme y devolvería un texto, sin acceso
 * a la base de datos ni capacidad de introducir cifras nuevas. Hoy la
 * plataforma no tiene proveedor de IA configurado, así que la implementación
 * vigente es una plantilla determinista.
 */
interface RedactorInforme
{
    /** Clave estable del redactor, para idempotencia y trazabilidad. */
    public function clave(): string;

    /**
     * Produce los textos del informe (resumen, conclusiones, recomendaciones) a
     * partir de la evidencia verificada.
     *
     * @return array{resumen: string, conclusiones: array<int, string>, recomendaciones: array<int, string>}
     */
    public function redactar(EvidenciaInforme $evidencia): array;
}
