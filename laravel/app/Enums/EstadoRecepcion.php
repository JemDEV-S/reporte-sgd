<?php

namespace App\Enums;

/**
 * Dominio de recepción del destino en el SGD, usado por el reporte de
 * pendientes. Explica el saldo del reporte; no es una clasificación
 * universal para todos los reportes de la plataforma.
 */
enum EstadoRecepcion: string
{
    case NoLeido = '0';
    case RecibidoSinAtender = '1';

    public function nombre(): string
    {
        return match ($this) {
            self::NoLeido => 'No leídos',
            self::RecibidoSinAtender => 'Recibidos sin atender',
        };
    }

    /** Estados que forman el saldo de pendientes del perfil institucional. */
    public static function pendientes(): array
    {
        return [self::NoLeido->value, self::RecibidoSinAtender->value];
    }
}
