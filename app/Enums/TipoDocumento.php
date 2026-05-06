<?php

namespace App\Enums;

enum TipoDocumento: string
{
    case CC  = 'CC';
    case NIT = 'NIT';
    case CE  = 'CE';
    case TI  = 'TI';
    case PAS = 'PAS';

    public function label(): string
    {
        return match($this) {
            self::CC  => 'Cédula de Ciudadanía',
            self::NIT => 'NIT',
            self::CE  => 'Cédula de Extranjería',
            self::TI  => 'Tarjeta de Identidad',
            self::PAS => 'Pasaporte',
        };
    }
}
