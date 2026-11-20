<?php

namespace App\Enums;

/**
 * Resultado de contrastar lo que encontró el auditor con lo que reportó el técnico en Survey123.
 */
enum ResultadoComparacion: string
{
    case Conforme = 'conforme';
    case NoConforme = 'no_conforme';
    case NoVerificable = 'no_verificable';

    public function label(): string
    {
        return match ($this) {
            self::Conforme => 'Conforme',
            self::NoConforme => 'No conforme',
            self::NoVerificable => 'No verificable',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Conforme => 'success',
            self::NoConforme => 'danger',
            self::NoVerificable => 'warning',
        };
    }
}
