<?php

namespace App\Http\Requests\Api;

use App\Enums\EstadoAuditoria;
use App\Models\auditoria;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AuditoriaGuardarRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var auditoria $auditoria */
        $auditoria = $this->route('auditoria');
        $user = $this->user();

        return $user->can('auditorias.ver')
            || $auditoria->reporte?->ordenJob?->personals_id === $user->personals_id;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'evidencias' => ['required', 'array', 'min:1'],
            'evidencias.*' => ['string', 'max:500'],
            'resultado' => ['required', Rule::in([
                EstadoAuditoria::Confirmado->value,
                EstadoAuditoria::Negativo->value,
            ])],
            'tipo_anomalia_id' => ['nullable', 'uuid', 'exists:tipos_anomalia,id'],
            'metros_cubicos_recuperados' => ['nullable', 'numeric'],
            'contrato' => ['nullable', 'string', 'max:255'],
            'observaciones' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
