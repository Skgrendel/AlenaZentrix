<?php

namespace App\Http\Requests\Api;

use App\Enums\EstadoAuditoria;
use App\Models\auditoria;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AuditoriaGuardarRequest extends FormRequest
{
    /**
     * Solo quien tiene `auditorias.registrar` y el servicio entre sus auditables puede registrar el
     * resultado (el técnico dueño de la orden no puede autoauditarse). La auditoría debe seguir pendiente.
     */
    public function authorize(): bool
    {
        /** @var auditoria $auditoria */
        $auditoria = $this->route('auditoria');
        $user = $this->user();

        $orden = $auditoria->reporte?->ordenJob;

        abort_if($orden === null, 404);

        if (! $user->can('auditorias.registrar')) {
            return false;
        }

        if (
            filled($user->personals_id)
            && ($orden->personals_id === $user->personals_id || $auditoria->reporte?->personals_id === $user->personals_id)
        ) {
            return false;
        }

        $esAuditable = $user->serviciosAuditables()
            ->where('tipo_servicio', $orden->tipo_servicio->value)
            ->exists();

        if (! $esAuditable) {
            return false;
        }

        abort_if($auditoria->estado !== EstadoAuditoria::PendienteAuditoria, 409, 'Esta auditoría ya fue registrada.');

        return true;
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
                    $path = (string) $value;

                    if (blank($companyId) || ! str_starts_with($path, $companyId.'/')) {
                        $fail('La evidencia debe pertenecer a tu empresa.');

                        return;
                    }

                    $segmentos = explode('/', $path);

                    if (
                        str_contains($path, '..')
                        || str_contains($path, '\\')
                        || str_contains($path, '//')
                        || str_contains($path, "\0")
                        || in_array('.', $segmentos, true)
                        || end($segmentos) === ''
                    ) {
                        $fail('La ruta de la evidencia no es válida.');
                    }
                },
            ],
            'resultado' => ['required', Rule::in([
                EstadoAuditoria::Confirmado->value,
                EstadoAuditoria::Negativo->value,
            ])],
            'tipo_anomalia_id' => [
                'nullable', 'uuid',
                Rule::exists('tipos_anomalia', 'id')->where('company_id', $companyId),
            ],
            'metros_cubicos_recuperados' => ['nullable', 'numeric'],
            'capacidad_instalada' => ['nullable', 'numeric'],
            'contrato' => ['nullable', 'string', 'max:255'],
            'observaciones' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
