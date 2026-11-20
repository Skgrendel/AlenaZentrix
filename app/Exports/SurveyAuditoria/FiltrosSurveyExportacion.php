<?php

namespace App\Exports\SurveyAuditoria;

use App\Enums\EstadoSurveyRegistro;
use App\Models\SurveyRegistro;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Filtros de la exportación de registros de Survey123. La empresa es obligatoria:
 * la consulta nunca se arma sin ella, sea cual sea el usuario o el comando que exporte.
 */
final class FiltrosSurveyExportacion
{
    /**
     * @param  array<int, string>|null  $ids
     */
    public function __construct(
        public readonly string $companyId,
        public readonly ?EstadoSurveyRegistro $estado = null,
        public readonly bool $soloConErrores = false,
        public readonly bool $soloConAlertas = false,
        public readonly ?Carbon $desde = null,
        public readonly ?Carbon $hasta = null,
        public readonly ?string $tecnico = null,
        public readonly ?array $ids = null,
    ) {}

    /**
     * Los filtros aplicados, en frases cortas para la portada del informe.
     *
     * @return array<int, string>
     */
    public function descripcion(): array
    {
        $lineas = [];

        if ($this->estado !== null) {
            $lineas[] = "Estado: {$this->estado->label()}";
        }

        if ($this->soloConErrores) {
            $lineas[] = 'Solo registros con errores';
        }

        if ($this->soloConAlertas) {
            $lineas[] = 'Solo registros con alertas';
        }

        if ($this->desde !== null || $this->hasta !== null) {
            $lineas[] = 'Fecha de registro: '.($this->desde?->format('d/m/Y') ?? 'sin inicio').' a '.($this->hasta?->format('d/m/Y') ?? 'hoy');
        }

        if (filled($this->tecnico)) {
            $lineas[] = "Técnico: {$this->tecnico}";
        }

        if ($this->ids !== null) {
            $lineas[] = 'Solo '.count($this->ids).' registro(s) elegidos';
        }

        return $lineas;
    }

    /** @return Builder<SurveyRegistro> */
    public function query(): Builder
    {
        return SurveyRegistro::query()
            ->withoutGlobalScopes()
            ->where('survey_registros.company_id', $this->companyId)
            ->when($this->estado, fn (Builder $q) => $q->where('survey_registros.estado', $this->estado->value))
            ->when($this->soloConErrores, fn (Builder $q) => $q->where('survey_registros.total_errores', '>', 0))
            ->when($this->soloConAlertas, fn (Builder $q) => $q->where('survey_registros.total_alertas', '>', 0))
            ->when($this->desde, fn (Builder $q) => $q->where('survey_registros.fecha_registro', '>=', $this->inicioDelDia($this->desde)))
            ->when($this->hasta, fn (Builder $q) => $q->where('survey_registros.fecha_registro', '<=', $this->finDelDia($this->hasta)))
            ->when(filled($this->tecnico), fn (Builder $q) => $q->where('survey_registros.tecnico', 'like', '%'.addcslashes((string) $this->tecnico, '%_\\').'%'))
            ->when($this->ids !== null, fn (Builder $q) => $q->whereIn('survey_registros.id', $this->ids));
    }

    private function inicioDelDia(Carbon $dia): Carbon
    {
        return $dia->copy()->shiftTimezone(config('survey_auditoria.zona_horaria_excel'))->startOfDay()->setTimezone(config('app.timezone'));
    }

    private function finDelDia(Carbon $dia): Carbon
    {
        return $dia->copy()->shiftTimezone(config('survey_auditoria.zona_horaria_excel'))->endOfDay()->setTimezone(config('app.timezone'));
    }
}
