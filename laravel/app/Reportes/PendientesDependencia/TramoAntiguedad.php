<?php

namespace App\Reportes\PendientesDependencia;

/**
 * Tramos de antigüedad del reporte, sin solapamientos: 0–3, 4–7, 8–15, 16–30 y
 * más de 30 días. Una fecha futura (recepción posterior al corte) es una
 * incidencia, no un tramo normal.
 */
enum TramoAntiguedad: string
{
    case Incidencia = 'incidencia';
    case Dias0a3 = '0-3';
    case Dias4a7 = '4-7';
    case Dias8a15 = '8-15';
    case Dias16a30 = '16-30';
    case Mas30 = '30+';

    /** Clasifica una cantidad de días (null = sin fecha de referencia). */
    public static function desde(?int $dias): self
    {
        if ($dias === null) {
            return self::Incidencia;
        }

        return match (true) {
            $dias < 0 => self::Incidencia,
            $dias <= 3 => self::Dias0a3,
            $dias <= 7 => self::Dias4a7,
            $dias <= 15 => self::Dias8a15,
            $dias <= 30 => self::Dias16a30,
            default => self::Mas30,
        };
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Incidencia => 'Sin fecha / futura',
            self::Dias0a3 => '0 a 3 días',
            self::Dias4a7 => '4 a 7 días',
            self::Dias8a15 => '8 a 15 días',
            self::Dias16a30 => '16 a 30 días',
            self::Mas30 => 'Más de 30 días',
        };
    }

    /** Orden estable para recorrer los tramos en la presentación. */
    public static function ordenados(): array
    {
        return [self::Dias0a3, self::Dias4a7, self::Dias8a15, self::Dias16a30, self::Mas30, self::Incidencia];
    }
}
