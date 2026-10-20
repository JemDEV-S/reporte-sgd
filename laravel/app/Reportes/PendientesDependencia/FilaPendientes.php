<?php

namespace App\Reportes\PendientesDependencia;

/**
 * Cifras de pendientes de una dependencia. Las cifras "propias" son las de
 * los destinos dirigidos a esa dependencia; las "acumuladas" suman además
 * las de todas sus dependencias descendientes (roll-up jerárquico).
 *
 * Un documento puede aportar varios destinos: la unidad contada es la
 * asignación (destino), nunca el documento único entre oficinas.
 */
class FilaPendientes
{
    public function __construct(
        public readonly string $codigo,
        public readonly string $nombre,
        public readonly int $nivel,
        public readonly bool $activa,
        public readonly ?string $codigoPadre,
        // Propias: solo los destinos de esta dependencia.
        public int $entradaNoLeidos = 0,
        public int $entradaRecibidos = 0,
        public int $delegadosNoLeidos = 0,
        public int $delegadosRecibidos = 0,
        public int $prioridadNormal = 0,
        public int $prioridadUrgente = 0,
        public int $prioridadMuyUrgente = 0,
        // Acumuladas: esta dependencia más toda su descendencia.
        public int $accEntradaNoLeidos = 0,
        public int $accEntradaRecibidos = 0,
        public int $accDelegadosNoLeidos = 0,
        public int $accDelegadosRecibidos = 0,
        public int $accPrioridadNormal = 0,
        public int $accPrioridadUrgente = 0,
        public int $accPrioridadMuyUrgente = 0,
        /** @var array<int, string> códigos de las dependencias hijas directas */
        public array $hijas = [],
    ) {}

    public function entradaTotal(): int
    {
        return $this->entradaNoLeidos + $this->entradaRecibidos;
    }

    public function delegadosTotal(): int
    {
        return $this->delegadosNoLeidos + $this->delegadosRecibidos;
    }

    public function total(): int
    {
        return $this->entradaTotal() + $this->delegadosTotal();
    }

    public function accEntradaTotal(): int
    {
        return $this->accEntradaNoLeidos + $this->accEntradaRecibidos;
    }

    public function accDelegadosTotal(): int
    {
        return $this->accDelegadosNoLeidos + $this->accDelegadosRecibidos;
    }

    public function accTotal(): int
    {
        return $this->accEntradaTotal() + $this->accDelegadosTotal();
    }
}
