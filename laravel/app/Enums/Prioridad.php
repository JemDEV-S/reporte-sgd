<?php

namespace App\Enums;

/**
 * Prioridad del destino. Los niveles urgente y muy urgente son un
 * subconjunto del total, no un sumando adicional ni señal de vencimiento.
 */
enum Prioridad: string
{
    case Normal = '1';
    case Urgente = '2';
    case MuyUrgente = '3';

    public function nombre(): string
    {
        return match ($this) {
            self::Normal => 'Normal',
            self::Urgente => 'Urgente',
            self::MuyUrgente => 'Muy urgente',
        };
    }

    /** Color convencional observado en el SGD (azul / naranja / rojo). */
    public function color(): string
    {
        return match ($this) {
            self::Normal => 'azul',
            self::Urgente => 'naranja',
            self::MuyUrgente => 'rojo',
        };
    }
}
