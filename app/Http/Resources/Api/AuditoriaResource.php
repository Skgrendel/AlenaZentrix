<?php

namespace App\Http\Resources\Api;

use App\Models\auditoria;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditoriaResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var auditoria $auditoria */
        $auditoria = $this->resource;

        return [
            'id' => $auditoria->id,
            'estado' => [
                'value' => $auditoria->estado->value,
                'label' => $auditoria->estado->label(),
                'color' => $auditoria->estado->color(),
            ],
            'evidencias' => $auditoria->evidencias ?? [],
            'observaciones' => $auditoria->observaciones,
            'tipo_anomalia_id' => $auditoria->tipo_anomalia_id,
            'metros_cubicos_recuperados' => $auditoria->metros_cubicos_recuperados,
            'capacidad_instalada' => $auditoria->capacidad_instalada,
            'fecha_auditoria' => $auditoria->fecha_auditoria?->toISOString(),
            'reporte' => $this->when(
                $auditoria->relationLoaded('reporte') && $auditoria->reporte,
                fn () => new ReporteResource($auditoria->reporte)
            ),
            'orden' => $this->when(
                $auditoria->relationLoaded('reporte')
                    && $auditoria->reporte
                    && $auditoria->reporte->relationLoaded('ordenJob')
                    && $auditoria->reporte->ordenJob,
                fn () => new OrdenResource($auditoria->reporte->ordenJob)
            ),
        ];
    }
}
