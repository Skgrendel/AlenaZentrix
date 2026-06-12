<?php

namespace App\Http\Resources\Api;

use App\Models\reportes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReporteResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var reportes $reporte */
        $reporte = $this->resource;

        return [
            'id' => $reporte->id,
            'orden_job_id' => $reporte->orden_job_id,
            'numero_orden' => $reporte->numero_orden,
            'numero_medidor' => $reporte->numero_medidor,
            'coincide_medidor' => $reporte->coincide_medidor,
            'lectura' => $reporte->lectura,
            'anomalia' => $reporte->anomalia ?? [],
            'hay_fuga_gas' => $reporte->hay_fuga_gas,
            'excede_capacidad' => $reporte->excede_capacidad,
            'imagenes' => $reporte->imagenes ?? [],
            'video' => $reporte->video,
            'geo_referencia' => $reporte->geo_referencia,
            'observaciones' => $reporte->observaciones,
            'comentarios' => $reporte->comentarios,
            'conclusion' => $reporte->conclusion,
            'actividades' => $reporte->actividades ?? [],
            'datos_servicio' => $reporte->datos_servicio ?? [],
            'estado' => $reporte->estado,
            'revisado' => $reporte->revisado,
            'confirmado' => $reporte->confirmado,
            'ubicacion' => $this->when(
                $reporte->relationLoaded('ubicacion') && $reporte->ubicacion,
                fn () => [
                    'latitud' => $reporte->ubicacion->latitud,
                    'longitud' => $reporte->ubicacion->longitud,
                    'direccion' => $reporte->ubicacion->direccion,
                ]
            ),
            'created_at' => $reporte->created_at->toISOString(),
        ];
    }
}
