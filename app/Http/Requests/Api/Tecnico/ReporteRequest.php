<?php

namespace App\Http\Requests\Api\Tecnico;

use App\Enums\TipoServicio;
use App\Models\OrdenJob;
use Illuminate\Foundation\Http\FormRequest;

class ReporteRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var OrdenJob $orden */
        $orden = $this->route('orden');
        $user = $this->user();

        return $user->can('ordenes.ver')
            || $orden->personals_id === $user->personals_id
            || $orden->tipo_servicio === TipoServicio::Vigilancia;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var OrdenJob $orden */
        $orden = $this->route('orden');

        $base = [
            'latitud' => ['nullable', 'numeric', 'between:-90,90'],
            'longitud' => ['nullable', 'numeric', 'between:-180,180'],
            'observaciones' => ['nullable', 'string', 'max:5000'],
            'imagenes' => ['nullable', 'array'],
            'imagenes.*' => ['string', 'max:500'],
            'video' => ['nullable', 'url', 'max:500'],
            'geo_referencia' => ['nullable', 'url', 'max:500'],
            'actividades' => ['nullable', 'array'],
            'actividades.*.uuid' => ['nullable', 'uuid'],
            'actividades.*.descripcion_orden' => ['nullable', 'string', 'max:500'],
            'actividades.*.actividades' => ['nullable', 'string', 'max:1000'],
        ];

        return match ($orden->tipo_servicio) {
            TipoServicio::Fraudes,
            TipoServicio::RevisionMedidores => array_merge($base, $this->reglesFraudes()),
            default => array_merge($base, ['datos_servicio' => ['nullable', 'array']]),
        };
    }

    /** @return array<string, mixed> */
    private function reglesFraudes(): array
    {
        return [
            'nombre_comercio' => ['required', 'string', 'max:255'],
            'tipo_comercio_id' => ['required', 'uuid', 'exists:tipo_comercios,id'],
            'numero_medidor' => ['nullable', 'string', 'max:255'],
            'coincide_medidor' => ['nullable', 'boolean'],
            'lectura' => ['nullable', 'string', 'max:255'],
            'marca_regulador_id' => ['nullable', 'uuid', 'exists:marca_reguladors,id'],
            'tipo_regulador_id' => ['nullable', 'uuid', 'exists:tipo_reguladors,id'],
            'marca_medidor_id' => ['nullable', 'uuid', 'exists:marca_medidors,id'],
            'tipo_notificacion_alerta_id' => ['nullable', 'uuid', 'exists:tipo_notificacion_alertas,id'],
            'anomalia' => ['nullable', 'array'],
            'anomalia.*' => ['uuid', 'exists:tipo_anomalias,id'],
            'tipo_imposibilidad_id' => ['nullable', 'uuid', 'exists:tipo_imposibilidads,id'],
            'hay_fuga_gas' => ['required', 'boolean'],
            'excede_capacidad' => ['required', 'boolean'],
        ];
    }
}
