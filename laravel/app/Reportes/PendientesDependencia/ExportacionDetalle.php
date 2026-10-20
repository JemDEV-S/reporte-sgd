<?php

namespace App\Reportes\PendientesDependencia;

use Illuminate\Support\Carbon;

/**
 * Define QUÉ se exporta del detalle de pendientes de una dependencia: el
 * encabezado de metadatos (publicación, filtros, alcance), los nombres de las
 * columnas y la proyección de cada fila a valores de texto.
 *
 * No decide el FORMATO del archivo. Hoy el controlador la usa para emitir CSV;
 * una exportación .xlsx futura (p. ej. con phpoffice/phpspreadsheet) puede
 * reutilizar exactamente estas mismas filas y cabeceras sin duplicar reglas.
 *
 * Las filas provienen de MotorPendientesDependencia::filasDetalle(), que deriva
 * de la misma consulta que la tabla del detalle: las cifras del archivo y las
 * de la pantalla son idénticas por construcción.
 */
class ExportacionDetalle
{
    private const ESTADO = ['0' => 'No leído', '1' => 'Recibido sin atender'];

    private const PRIORIDAD = ['1' => 'Normal', '2' => 'Urgente', '3' => 'Muy urgente'];

    public function __construct(
        private readonly MotorPendientesDependencia $motor,
        private readonly DetallePendientes $detalle,
        private readonly ContextoReporte $contexto,
        /** Restricción opcional de destinatario (exportar la selección de una persona). */
        private readonly ?string $destinatario = null,
    ) {}

    /**
     * Columnas de datos del detalle, en orden. Añaden los campos documentales
     * legibles y mantienen separadas las claves técnicas, códigos, fecha de
     * recepción y fuente de antigüedad. El asunto solo aparece con permiso.
     *
     * @return array<int, string>
     */
    public function columnas(): array
    {
        $columnas = [
            'Tipo (código)',
            'Tipo (descripción)',
            'N.º de documento',
            'Número incompleto',
            'Fecha de emisión',
        ];
        if ($this->contexto->puedeVerAsuntos) {
            $columnas[] = 'Asunto';
        }
        $columnas[] = 'Origen / remitente';
        if ($this->contexto->puedeVerPersonales) {
            $columnas[] = 'Destinatario (nombre)';
            $columnas[] = 'Destinatario (código)';
        }

        return array_merge($columnas, [
            'Dependencia',
            'Bandeja',
            'Estado de recepción',
            'Prioridad',
            'Antigüedad (días)',
            'Fuente de antigüedad',
            'Tramo de antigüedad',
            'Fecha de recepción',
            'Clave técnica (año-emisión-destino)',
        ]);
    }

    /**
     * Proyecta cada asignación pendiente a una fila de texto, en el orden de las
     * columnas. Recorre de forma perezosa todos los destinos del detalle, según
     * la misma restricción (filtros, destinatario) que la pantalla.
     *
     * @return \Generator<int, array<int, string>>
     */
    public function filas(): \Generator
    {
        foreach ($this->motor->filasDetalle($this->detalle->codigo, $this->destinatario) as $fila) {
            $valores = [
                (string) ($fila->tipoCodigo ?? ''),
                (string) ($fila->tipoDescripcion ?? ''),
                (string) ($fila->numeroDocumento ?? ''),
                $fila->numeroIncompleto ? 'Sí' : '',
                $fila->fechaEmision?->format('d/m/Y') ?? '',
            ];
            if ($this->contexto->puedeVerAsuntos) {
                $valores[] = (string) ($fila->asunto ?? '');
            }
            $valores[] = (string) ($fila->origen ?? '');
            if ($this->contexto->puedeVerPersonales) {
                $valores[] = (string) ($fila->destinatarioNombre ?? '');
                $valores[] = (string) ($fila->destinatarioCodigo ?? '');
            }

            yield array_merge($valores, [
                $fila->dependenciaCodigo,
                $fila->bandeja(),
                $fila->estadoNombre(),
                $fila->prioridadNombre() ?? '',
                $fila->dias === null ? '' : (string) $fila->dias,
                $fila->antiguedadPorEmision ? 'Emisión (sustituta)' : 'Recepción',
                $fila->tramo->etiqueta(),
                $fila->fechaRecepcion?->format('d/m/Y H:i') ?? '',
                $fila->claveTecnica(),
            ]);
        }
    }

    /**
     * Encabezado de contexto que acompaña al archivo para que las cifras sean
     * auditables: qué dependencia, qué publicación, con qué perfil y filtros, a
     * qué fecha de corte y bajo qué unidad de conteo se generó.
     *
     * @return array<int, array<int, string>>
     */
    public function metadatos(): array
    {
        $publicacion = $this->detalle->publicacion;

        $lineas = [
            ['Reporte', 'Pendientes por dependencia — detalle'],
            ['Dependencia', $this->detalle->codigo.' · '.$this->detalle->nombre()],
            ['Total de destinos', (string) $this->detalle->totalDestinos()],
            ['Unidad', 'Asignaciones (destinos internos, tipo 01)'],
            ['Perfil', ucfirst($publicacion->perfil)],
            ['Publicación', Carbon::parse($publicacion->fecha_publicacion)->translatedFormat('d \d\e F \d\e Y, H:i')],
            ['Fecha de corte', $this->detalle->fechaCorte->translatedFormat('d \d\e F \d\e Y')],
            ['Generado', Carbon::now()->translatedFormat('d \d\e F \d\e Y, H:i')],
            ['Ámbito de campos', $this->ambitoCampos()],
        ];

        $descripcion = $this->detalle->filtros->descripcion();
        $lineas[] = ['Filtros', $descripcion === [] ? 'Sin filtros' : $this->filtrosLegibles($descripcion)];

        return $lineas;
    }

    /**
     * Nombre de archivo sugerido, sin extensión. El controlador añade la
     * extensión según el formato emitido.
     */
    public function nombreBase(): string
    {
        $base = 'pendientes_'.$this->detalle->codigo.'_'.$this->detalle->fechaCorte->format('Ymd');

        return $this->destinatario !== null ? $base.'_emp'.$this->destinatario : $base;
    }

    /** Describe qué campos sensibles incluye el archivo, según permisos. */
    private function ambitoCampos(): string
    {
        $partes = ['Institucional'];
        $partes[] = $this->contexto->puedeVerAsuntos ? 'con asuntos' : 'sin asuntos';
        $partes[] = $this->contexto->puedeVerPersonales ? 'con datos personales' : 'sin datos personales';

        return implode(' · ', $partes);
    }

    /** @param array<string, string|null> $descripcion */
    private function filtrosLegibles(array $descripcion): string
    {
        $partes = [];
        foreach ($descripcion as $campo => $valor) {
            $legible = match ($campo) {
                'Estado de recepción' => self::ESTADO[$valor] ?? $valor,
                'Prioridad' => self::PRIORIDAD[$valor] ?? $valor,
                'Bandeja' => ucfirst((string) $valor),
                default => $valor,
            };
            $partes[] = "{$campo}: {$legible}";
        }

        return implode(' · ', $partes);
    }
}
