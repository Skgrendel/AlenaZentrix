<?php

namespace App\Services\survey123;

use App\Services\coordinador\DataGisServices;

/**
 * Trae del GIS de Surtigas los datos básicos del usuario (nombre, dirección y barrio)
 * para completar la orden de auditoría. No guarda nada: solo consulta.
 */
class ConsultaGisContrato
{
    public function __construct(private readonly DataGisServices $gis) {}

    /**
     * @return array{nombre: ?string, direccion: ?string, barrio: ?string}|null null si el contrato no existe o el GIS no responde.
     */
    public function datosBasicos(?string $contrato): ?array
    {
        if (blank($contrato)) {
            return null;
        }

        $respuesta = $this->gis->consultarContrato($contrato);

        if (isset($respuesta['error'])) {
            return null;
        }

        return [
            'nombre' => $respuesta['titular']['nombre'] ?? null,
            'direccion' => $respuesta['ubicacion']['direccion'] ?? null,
            'barrio' => $respuesta['ubicacion']['barrio'] ?? $respuesta['ubicacion']['municipio'] ?? null,
        ];
    }
}
