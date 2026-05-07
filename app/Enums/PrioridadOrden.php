<?php

namespace App\Enums;

/**
 * Nivel de prioridad de una orden de trabajo, usado para ordenar la carga de trabajo del técnico.
 */
enum PrioridadOrden: string
{
    case Baja = 'baja';
    case Media = 'media';
    case Alta = 'alta';
    case Urgente = 'urgente';

    public function label(): string
    {
        return match ($this) {
            self::Baja => 'Baja',
            self::Media => 'Media',
            self::Alta => 'Alta',
            self::Urgente => 'Urgente',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Baja => 'gray',
            self::Media => 'info',
            self::Alta => 'warning',
            self::Urgente => 'danger',
        };
    }
}
