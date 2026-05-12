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
    case Capturas = 'capturas';
    case Bandas = 'bandas';
    case Vigilancia = 'vigilancia';
    case Gestion_Riesgo = 'Gestión de Riesgo';

    public function label(): string
    {
        return match ($this) {
            self::Fraudes => 'Fraudes',
            self::Investigacion => 'Investigación',
            self::Capturas => 'Capturas Criminales',
            self::Bandas => 'Desarticulación de Bandas',
            self::Vigilancia => 'Dispositivos de Vigilancia',
            self::Gestion_Riesgo => 'Gestión de Riesgo',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Fraudes => 'danger',
            self::Investigacion => 'warning',
            self::Capturas => 'info',
            self::Bandas => 'purple',
            self::Vigilancia => 'success',
            self::Gestion_Riesgo => 'primary',
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
        return in_array($this, [self::Fraudes, self::Investigacion, self::Vigilancia]);
    }

    /** @return bool Servicios que tienen datos de medidor */
    public function tieneMedidor(): bool
    {
        return $this === self::Fraudes;
    }

    /** @return bool Servicios que tienen número de noticia criminal */
    public function tieneNoticiaCriminal(): bool
    {
        return in_array($this, [self::Capturas, self::Bandas]);
    }
}
