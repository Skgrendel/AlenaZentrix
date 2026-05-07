<?php

namespace App\Enums;

/**
 * Ciclo de vida de una orden de trabajo: pendiente → asignada → en_progreso → en_revision → completada/rechazada/cancelada.
 */
enum EstadoOrden: string
{
    case Pendiente = 'pendiente';
    case Asignada = 'asignada';
    case EnProgreso = 'en_progreso';
    case EnRevision = 'en_revision';
    case Completada = 'completada';
    case Rechazada = 'rechazada';
    case Cancelada = 'cancelada';

    public function label(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Asignada => 'Asignada',
            self::EnProgreso => 'En Progreso',
            self::EnRevision => 'En Revisión',
            self::Completada => 'Completada',
            self::Rechazada => 'Rechazada',
            self::Cancelada => 'Cancelada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pendiente => 'gray',
            self::Asignada => 'info',
            self::EnProgreso => 'warning',
            self::EnRevision => 'purple',
            self::Completada => 'success',
            self::Rechazada => 'danger',
            self::Cancelada => 'gray',
        };
    }
}
