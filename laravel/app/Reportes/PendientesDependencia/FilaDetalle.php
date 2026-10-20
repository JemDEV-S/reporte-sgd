<?php

namespace App\Reportes\PendientesDependencia;

use App\Models\DestinoCaptura;
use Illuminate\Support\Carbon;

/**
 * Presentador de una fila del detalle (§8). Expone solo los campos ya resueltos
 * y permitidos, de modo que ni la pantalla ni la exportación accedan a atributos
 * crudos de Eloquent ni ejecuten consultas por fila.
 *
 * Los campos sensibles (asunto, nombres personales de destinatario/origen) se
 * proyectan según los permisos del contexto: si no están permitidos, el valor es
 * null y la capa de presentación muestra "Restringido". El asunto, además, NO se
 * selecciona en SQL cuando falta el permiso (ver el motor).
 *
 * La clave técnica (año-emisión-destino) se conserva siempre para trazabilidad,
 * como información secundaria, NUNCA como sustituto del número documental.
 */
class FilaDetalle
{
    private const ESTADO = ['0' => 'No leído', '1' => 'Recibido sin atender'];

    private const PRIORIDAD = ['1' => 'Normal', '2' => 'Urgente', '3' => 'Muy urgente'];

    private function __construct(
        // Clave técnica de la asignación.
        public readonly string $anio,
        public readonly string $emision,
        public readonly string $destino,
        public readonly string $dependenciaCodigo,
        // Tipo documental.
        public readonly ?string $tipoCodigo,
        public readonly ?string $tipoDescripcion,
        // Número documental visible.
        public readonly ?string $numeroDocumento,
        public readonly bool $numeroIncompleto,
        // Fechas.
        public readonly ?Carbon $fechaEmision,
        public readonly ?Carbon $fechaRecepcion,
        // Asunto (permitido o null; permiso faltante o ausencia marcados aparte).
        public readonly ?string $asunto,
        public readonly bool $asuntoRestringido,
        // Origen / remitente.
        public readonly ?string $origen,
        public readonly bool $origenRestringido,
        // Destinatario.
        public readonly ?string $destinatarioNombre,
        public readonly ?string $destinatarioCodigo,
        public readonly bool $destinatarioRestringido,
        // Clasificación y saldo.
        public readonly bool $esEntrada,
        public readonly string $estadoCodigo,
        public readonly ?string $prioridadCodigo,
        // Antigüedad.
        public readonly TramoAntiguedad $tramo,
        public readonly ?int $dias,
        public readonly bool $antiguedadPorEmision,
    ) {}

    /**
     * Construye la fila desde un destino capturado (con las columnas del join a
     * documentos/tipos/empleados ya seleccionadas) y el contexto de permisos.
     */
    public static function desde(DestinoCaptura $d, Carbon $corte, ContextoReporte $contexto): self
    {
        $recepcion = $d->fecha_recepcion ? Carbon::parse($d->fecha_recepcion) : null;
        $emision = isset($d->fecha_emision_doc) && $d->fecha_emision_doc !== null
            ? Carbon::parse($d->fecha_emision_doc)
            : null;

        $referencia = $recepcion ?? $emision;
        $dias = $referencia === null
            ? null
            : (int) $referencia->copy()->startOfDay()->diffInDays($corte->copy()->startOfDay(), false);

        // Asunto: solo con permiso. Sin permiso → restringido (y el motor ni lo
        // selecciona). Con permiso pero vacío → null (la vista muestra fallback).
        $asuntoRestringido = ! $contexto->puedeVerAsuntos;
        $asunto = $asuntoRestringido ? null : self::limpio($d->asunto ?? null);

        // Origen y destinatario: los nombres personales requieren permiso.
        $origenRestringido = ! $contexto->puedeVerPersonales;
        $origen = $origenRestringido ? null : self::limpio($d->origen_descripcion ?? null);

        $destinatarioRestringido = ! $contexto->puedeVerPersonales;
        $destinatarioNombre = $destinatarioRestringido ? null : self::nombreDestinatario($d);
        $destinatarioCodigo = $contexto->puedeVerPersonales ? self::limpio($d->destinatario_empleado) : null;

        return new self(
            anio: (string) $d->anio,
            emision: (string) $d->emision,
            destino: (string) $d->destino,
            dependenciaCodigo: (string) $d->dependencia_codigo,
            tipoCodigo: self::limpio($d->tipo_documental ?? null),
            tipoDescripcion: self::limpio($d->tipo_descripcion ?? null),
            numeroDocumento: self::limpio($d->numero_documento ?? null),
            numeroIncompleto: (bool) ($d->numero_incompleto ?? false),
            fechaEmision: $emision,
            fechaRecepcion: $recepcion,
            asunto: $asunto,
            asuntoRestringido: $asuntoRestringido,
            origen: $origen,
            origenRestringido: $origenRestringido,
            destinatarioNombre: $destinatarioNombre,
            destinatarioCodigo: $destinatarioCodigo,
            destinatarioRestringido: $destinatarioRestringido,
            esEntrada: $d->destinatario_empleado === $d->encargado_empleado,
            estadoCodigo: (string) $d->estado_recepcion,
            prioridadCodigo: $d->prioridad !== null ? (string) $d->prioridad : null,
            tramo: TramoAntiguedad::desde($dias),
            dias: $dias,
            antiguedadPorEmision: $recepcion === null && $emision !== null,
        );
    }

    public function bandeja(): string
    {
        return $this->esEntrada ? 'Entrada' : 'Delegados';
    }

    public function estadoNombre(): string
    {
        return self::ESTADO[$this->estadoCodigo] ?? $this->estadoCodigo;
    }

    public function prioridadNombre(): ?string
    {
        return $this->prioridadCodigo !== null ? (self::PRIORIDAD[$this->prioridadCodigo] ?? null) : null;
    }

    /** Clave técnica legible de la asignación, para trazabilidad. */
    public function claveTecnica(): string
    {
        return "{$this->anio}-{$this->emision}-{$this->destino}";
    }

    /**
     * Nombre del destinatario desde las columnas del join a empleados_captura,
     * sin ejecutar otra consulta. "APELLIDOS, Denominación".
     */
    private static function nombreDestinatario(DestinoCaptura $d): ?string
    {
        $apellidos = trim(implode(' ', array_filter([
            self::limpio($d->destinatario_apepat ?? null),
            self::limpio($d->destinatario_apemat ?? null),
        ])));
        $partes = array_filter([$apellidos, self::limpio($d->destinatario_denom ?? null)]);

        return $partes === [] ? null : implode(', ', $partes);
    }

    private static function limpio(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }
        $texto = trim((string) $valor);

        return $texto === '' ? null : $texto;
    }
}
