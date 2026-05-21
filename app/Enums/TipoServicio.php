<?php

namespace App\Enums;

/**
 * Clasificación del tipo de servicio de una orden de trabajo.
 * Determina el flujo, los campos del reporte y si requiere técnico asignado.
 * Vigilancia es el único tipo sin técnico fijo: los reportes se acumulan mientras la orden sigue EnProgreso.
 */
enum TipoServicio: string
{
    case Fraudes = 'fraudes';
    case Investigacion = 'investigacion';
    case GestionLegal = 'gestion_legal';
    case Vigilancia = 'vigilancia';
    case Gestion_Riesgo = 'Gestión de Riesgo';
    case AuditoriaCampo = 'auditoria_campo';
    case RevisionMedidores = 'revision_medidores';

    public function label(): string
    {
        return match ($this) {
            self::Fraudes => 'Fraudes',
            self::Investigacion => 'Investigación',
            self::GestionLegal => 'Gestión Legal',
            self::Vigilancia => 'Dispositivos de Vigilancia',
            self::Gestion_Riesgo => 'Gestión de Riesgo',
            self::AuditoriaCampo => 'Auditorías de Campo',
            self::RevisionMedidores => 'Revisión de Medidores',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Fraudes => 'danger',
            self::Investigacion => 'warning',
            self::GestionLegal => 'info',
            self::Vigilancia => 'success',
            self::Gestion_Riesgo => 'primary',
            self::AuditoriaCampo => 'purple',
            self::RevisionMedidores => 'cyan',
        };
    }

    /** @return bool Servicios que tienen técnico asignado fijo */
    public function tieneTecnicoAsignado(): bool
    {
        return $this !== self::Vigilancia;
    }

    /** @return bool Servicios que requieren contrato y cliente */
    public function tieneContrato(): bool
    {
        return in_array($this, [self::Fraudes, self::Investigacion, self::Vigilancia, self::RevisionMedidores]);
    }

    /** @return bool Servicios que tienen datos de medidor */
    public function tieneMedidor(): bool
    {
        return in_array($this, [self::Fraudes, self::RevisionMedidores]);
    }
}
