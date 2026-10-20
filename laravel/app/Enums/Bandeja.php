<?php

namespace App\Enums;

/**
 * Clasificación de acceso institucional del destino. "Delegados" significa
 * que el destinatario capturado difiere del encargado de la dependencia;
 * no acredita una delegación formal.
 */
enum Bandeja: string
{
    case Entrada = 'entrada';
    case Delegados = 'delegados';

    public function nombre(): string
    {
        return match ($this) {
            self::Entrada => 'Entrada',
            self::Delegados => 'Delegados',
        };
    }

    public function descripcion(): string
    {
        return match ($this) {
            self::Entrada => 'Destinos dirigidos al encargado actual de la dependencia.',
            self::Delegados => 'Destinos dirigidos a un empleado distinto del encargado capturado.',
        };
    }
}
