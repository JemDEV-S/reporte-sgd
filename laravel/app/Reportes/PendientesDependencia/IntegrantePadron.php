<?php

namespace App\Reportes\PendientesDependencia;

/**
 * Una entrada del padrón de integrantes de una dependencia (§5): una persona
 * activa con sus conteos de pendientes en esa oficina, o una entrada del grupo
 * "otras asignaciones" (destinatarios fuera del padrón activo, incluido el
 * destinatario nulo).
 *
 * Los conteos se calculan SIEMPRE por destinos.dependencia_codigo=D y
 * destinatario_empleado=E (una partición del reporte institucional), nunca
 * replicando la bandeja personal del SGD. Un integrante activo sin pendientes
 * aparece con todos los conteos en cero.
 *
 * El nombre es dato personal: solo se construye y expone con permiso; el motor
 * y el controlador no deben instanciar un IntegrantePadron con nombre si el
 * contexto no lo permite.
 */
class IntegrantePadron
{
    /**
     * @param  string|null  $codigo  Código de empleado; null para el grupo "sin destinatario".
     * @param  string|null  $nombre  Nombre legible, solo con permiso personal.
     * @param  array<int, string>  $vias  Vías de pertenencia ("principal", "configurada", "asignada").
     */
    private function __construct(
        public readonly ?string $codigo,
        public readonly ?string $nombre,
        public readonly bool $esEncargado,
        public readonly bool $enPadron,
        public readonly bool $esSinDestinatario,
        public readonly array $vias,
        public readonly int $total,
        public readonly int $noLeidos,
        public readonly int $recibidos,
        public readonly int $urgentes,
    ) {}

    /** Integrante del padrón activo de la dependencia (puede tener saldo cero). */
    public static function integrante(
        string $codigo,
        ?string $nombre,
        bool $esEncargado,
        array $vias,
        int $total,
        int $noLeidos,
        int $recibidos,
        int $urgentes,
    ): self {
        return new self(
            codigo: $codigo,
            nombre: $nombre,
            esEncargado: $esEncargado,
            enPadron: true,
            esSinDestinatario: false,
            vias: $vias,
            total: $total,
            noLeidos: $noLeidos,
            recibidos: $recibidos,
            urgentes: $urgentes,
        );
    }

    /**
     * Destinatario fuera del padrón activo (inactivo, trasladado o sin registro
     * de empleado). Siempre tiene al menos un pendiente: el grupo se arma por
     * anti-existencia contra el padrón.
     */
    public static function otraAsignacion(
        string $codigo,
        ?string $nombre,
        int $total,
        int $noLeidos,
        int $recibidos,
        int $urgentes,
    ): self {
        return new self(
            codigo: $codigo,
            nombre: $nombre,
            esEncargado: false,
            enPadron: false,
            esSinDestinatario: false,
            vias: [],
            total: $total,
            noLeidos: $noLeidos,
            recibidos: $recibidos,
            urgentes: $urgentes,
        );
    }

    /**
     * Pendientes con destinatario nulo: no se equiparan a una persona ni al
     * encargado (§5.3). Van al grupo "otras asignaciones" como "Sin destinatario
     * identificado".
     */
    public static function sinDestinatario(
        int $total,
        int $noLeidos,
        int $recibidos,
        int $urgentes,
    ): self {
        return new self(
            codigo: null,
            nombre: null,
            esEncargado: false,
            enPadron: false,
            esSinDestinatario: true,
            vias: [],
            total: $total,
            noLeidos: $noLeidos,
            recibidos: $recibidos,
            urgentes: $urgentes,
        );
    }

    /**
     * Motivo legible por el que esta entrada está fuera del padrón activo, para
     * mostrarlo en el grupo "otras asignaciones".
     */
    public function motivo(): ?string
    {
        if ($this->enPadron) {
            return null;
        }
        if ($this->esSinDestinatario) {
            return 'Sin destinatario identificado';
        }
        if ($this->nombre === null && $this->codigo !== null) {
            return 'Sin registro de empleado activo en esta oficina';
        }

        return 'Fuera del padrón activo de la oficina';
    }
}
