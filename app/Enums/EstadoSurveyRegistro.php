<?php

namespace App\Enums;

/**
 * Estado de un registro de Survey123 dentro del proceso de auditoría.
 */
enum EstadoSurveyRegistro: string
{
    case Pendiente = 'pendiente';
    case Asignado = 'asignado';

    public function label(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente por asignar',
            self::Asignado => 'Asignado a campo',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pendiente => 'warning',
            self::Asignado => 'success',
        };
    }
}
