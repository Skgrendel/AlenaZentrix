<?php

namespace App\Enums;

enum EstadoAuditoria: string
{
    case PendienteAuditoria = 'pendiente_auditoria';
    case Confirmado = 'confirmado';
    case Negativo = 'negativo';

    public function label(): string
    {
        return match ($this) {
            self::PendienteAuditoria => 'Pendiente por auditar',
            self::Confirmado => 'Confirmado',
            self::Negativo => 'Negativo',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PendienteAuditoria => 'warning',
            self::Confirmado => 'success',
            self::Negativo => 'danger',
        };
    }
}
