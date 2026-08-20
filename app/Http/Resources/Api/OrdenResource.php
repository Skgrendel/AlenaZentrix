<?php

namespace App\Http\Resources\Api;

use App\Enums\EstadoOrden;
use App\Enums\TipoServicio;
use App\Models\OrdenJob;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrdenResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var OrdenJob $orden */
        $orden = $this->resource;

        $acciones = $this->accionesDisponibles($orden, $request);

        return [
            'id' => $orden->id,
            'numero_orden' => $orden->numero_orden,
            'tipo_servicio' => [
                'value' => $orden->tipo_servicio->value,
                'label' => $orden->tipo_servicio->label(),
                'color' => $orden->tipo_servicio->color(),
            ],
            'estado' => [
                'value' => $orden->estado->value,
                'label' => $orden->estado->label(),
                'color' => $orden->estado->color(),
            ],
            'prioridad' => [
                'value' => $orden->prioridad->value,
                'label' => $orden->prioridad->label(),
                'color' => $orden->prioridad->color(),
            ],
            'contrato' => $orden->contrato,
            'cliente' => $orden->cliente,
            'direccion' => $orden->direccion,
            'barrio' => $orden->barrio,
            'medidor' => $orden->medidor,
            'tipo_medidor' => $orden->tipo_medidor,
            'ciclo' => $orden->ciclo,
            'objetivo' => $orden->objetivo,
            'antecedente' => $orden->antecedente,
            'comentario_usuario' => $orden->comentario_usuario,
            'actividades' => $orden->actividades ?? [],
            'documentos' => $orden->documentos ?? [],
            'proceso' => $orden->proceso_id ? [
                'id' => $orden->proceso_id,
                'nombre' => $this->when(
                    $orden->relationLoaded('proceso') && $orden->proceso,
                    fn () => $orden->proceso->nombre
                ),
            ] : null,
            'origen_informacion' => $orden->origen_informacion_id ? [
                'id' => $orden->origen_informacion_id,
                'nombre' => $this->when(
                    $orden->relationLoaded('origenInformacion') && $orden->origenInformacion,
                    fn () => $orden->origenInformacion->nombre
                ),
            ] : null,
            'fecha_asignacion' => $orden->fecha_asignacion?->toISOString(),
            'fecha_limite' => $orden->fecha_limite?->toISOString(),
            'fecha_completado' => $orden->fecha_completado?->toISOString(),
            'sincronizado' => $orden->sincronizado,
            'tiene_reporte' => $this->when(
                $request->routeIs('api.tecnico.ordenes.show'),
                fn () => $orden->reportes()->exists()
            ),
            'acciones' => $acciones,
            'created_at' => $orden->created_at->toISOString(),
        ];
    }

    /** @return array<string, bool> */
    private function accionesDisponibles(OrdenJob $orden, Request $request): array
    {
        $user = $request->user();

        return [
            'puede_ver' => $orden->estado !== EstadoOrden::EnProgreso,
            'puede_iniciar' => $orden->estado === EstadoOrden::Asignada
                && $orden->tipo_servicio !== TipoServicio::Vigilancia,
            'puede_reportar' => $orden->estado === EstadoOrden::EnProgreso
                && (
                    $orden->tipo_servicio === TipoServicio::Vigilancia
                    || $orden->reportes()->doesntExist()
                ),
            'puede_cerrar_vigilancia' => $orden->tipo_servicio === TipoServicio::Vigilancia
                && $orden->estado === EstadoOrden::EnProgreso,
            'puede_editar_reporte' => $orden->estado === EstadoOrden::Rechazada,
        ];
    }
}
