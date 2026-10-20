<?php

namespace App\Informes;

use App\Models\Informe;
use App\Models\Publicacion;
use App\Models\User;

/**
 * Genera informes de forma idempotente: para una misma publicación, alcance y
 * redactor, devuelve SIEMPRE el mismo informe en lugar de crear duplicados.
 * Reintentar la generación no multiplica borradores.
 *
 * Las cifras provienen de EvidenciaInforme (agregados verificados del motor);
 * el redactor solo redacta los textos. La evidencia y los textos se guardan
 * juntos, de modo que el documento Word se arma sin recalcular.
 */
class GeneradorInforme
{
    public const REPORTE = 'pendientes_dependencia';

    public function __construct(
        private readonly RedactorInforme $redactor = new RedactorPlantilla,
    ) {}

    /**
     * Devuelve el informe institucional de la publicación, creándolo como
     * borrador si aún no existe. Los datos de encabezado solo se aplican al
     * crearlo; un informe ya existente se devuelve sin modificar.
     *
     * @param  array<string, string|null>  $encabezado  numero, destinatario, destinatario_cargo, remitente, remitente_cargo, asunto
     */
    public function generar(Publicacion $publicacion, User $autor, array $encabezado = []): Informe
    {
        $clave = $this->claveIdempotencia($publicacion);

        $existente = Informe::firstWhere('idempotencia_clave', $clave);
        if ($existente !== null) {
            return $existente;
        }

        $evidencia = EvidenciaInforme::institucional($publicacion);
        $textos = $this->redactor->redactar($evidencia);

        // La evidencia guardada incluye los agregados y los textos redactados,
        // para reconstruir el documento sin recalcular.
        $payload = [...$evidencia->datos, 'textos' => $textos];

        // firstOrCreate evita duplicados en reintentos simultáneos: la unicidad
        // de la clave garantiza un único informe por alcance.
        return Informe::firstOrCreate(
            ['idempotencia_clave' => $clave],
            [
                'publicacion_id' => $publicacion->id,
                'generado_por' => $autor->id,
                'reporte' => self::REPORTE,
                'dependencia' => null,
                'estado' => 'borrador',
                'contenido' => $this->textoPlano($textos),
                'evidencia' => $payload,
                'redactor' => $this->redactor->clave(),
                'numero' => $encabezado['numero'] ?? null,
                'destinatario' => $encabezado['destinatario'] ?? null,
                'destinatario_cargo' => $encabezado['destinatario_cargo'] ?? null,
                'remitente' => $encabezado['remitente'] ?? null,
                'remitente_cargo' => $encabezado['remitente_cargo'] ?? null,
                'asunto' => $encabezado['asunto'] ?? null,
            ],
        );
    }

    /**
     * Texto plano del informe, para la vista previa web y la búsqueda.
     *
     * @param  array{resumen: string, conclusiones: array<int, string>, recomendaciones: array<int, string>}  $textos
     */
    private function textoPlano(array $textos): string
    {
        $lineas = ['RESUMEN GENERAL', $textos['resumen'], '', 'CONCLUSIONES'];
        foreach ($textos['conclusiones'] as $c) {
            $lineas[] = '- '.$c;
        }
        $lineas[] = '';
        $lineas[] = 'RECOMENDACIONES';
        foreach ($textos['recomendaciones'] as $r) {
            $lineas[] = '- '.$r;
        }

        return implode("\n", $lineas);
    }

    /**
     * Clave única por publicación + redactor. Fija el informe a una publicación
     * concreta: una publicación nueva produce un informe nuevo.
     */
    private function claveIdempotencia(Publicacion $publicacion): string
    {
        return implode(':', [
            self::REPORTE,
            'pub'.$publicacion->id,
            'institucional',
            $this->redactor->clave(),
        ]);
    }
}
