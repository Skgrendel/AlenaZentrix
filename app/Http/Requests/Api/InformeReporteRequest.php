<?php

namespace App\Http\Requests\Api;

use App\Enums\EstadoOrden;
use App\Enums\TipoServicio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InformeReporteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('informes');
    }

    public function rules(): array
    {
        return [
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'estado' => ['nullable', 'string', Rule::enum(EstadoOrden::class)],
            'servicio' => ['nullable', 'string', Rule::enum(TipoServicio::class)],
            'sub_tipo' => ['nullable', 'string'],
            'proceso' => ['nullable', 'uuid'],
            'origen_informacion' => ['nullable', 'uuid'],
        ];
    }

    /** @return array<string, string> Solo los filtros que vienen con valor */
    public function filters(): array
    {
        return array_filter([
            'fecha_inicio' => $this->input('fecha_inicio'),
            'fecha_fin' => $this->input('fecha_fin'),
            'estado' => $this->input('estado'),
            'servicio' => $this->input('servicio'),
            'sub_tipo' => $this->input('sub_tipo'),
            'proceso' => $this->input('proceso'),
            'origen_informacion' => $this->input('origen_informacion'),
        ]);
    }
}
