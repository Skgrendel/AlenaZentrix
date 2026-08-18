<?php

namespace App\Http\Requests\Api;

use App\Enums\EstadoAuditoria;
use App\Models\auditoria;
use Closure;
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
        $companyId = $this->user()->company_id;

        return [
            'evidencias' => ['required', 'array', 'min:1'],
            'evidencias.*' => [
                'string', 'max:500',
                function (string $attribute, mixed $value, Closure $fail) use ($companyId): void {
                    if (! str_starts_with((string) $value, $companyId.'/')) {
                        $fail('La evidencia debe pertenecer a tu empresa.');
                    }
                },
            ],
            'resultado' => ['required', Rule::in([
                EstadoAuditoria::Confirmado->value,
                EstadoAuditoria::Negativo->value,
            ])],
            'tipo_anomalia_id' => ['nullable', 'uuid', 'exists:tipos_anomalia,id'],
            'metros_cubicos_recuperados' => ['nullable', 'numeric'],
            'capacidad_instalada' => ['nullable', 'numeric'],
            'contrato' => ['nullable', 'string', 'max:255'],
            'observaciones' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
