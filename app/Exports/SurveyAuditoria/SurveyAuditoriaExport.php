<?php

namespace App\Exports\SurveyAuditoria;

use App\Models\SurveyRegistro;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Libro de la auditoría de suspensiones de Survey123: Auditoría, Visitas de auditoría y Resumen.
 *
 * Recibe la consulta de registros ya acotada por empresa y filtros; las tres hojas se derivan de ella.
 */
class SurveyAuditoriaExport implements WithMultipleSheets
{
    use Exportable;

    /** @param Builder<SurveyRegistro> $registros */
    public function __construct(private readonly Builder $registros) {}

    public static function conFiltros(FiltrosSurveyExportacion $filtros): self
    {
        return new self($filtros->query());
    }

    public static function nombreDeArchivo(): string
    {
        return 'survey123-suspensiones-'.now()->format('Ymd-His').'.xlsx';
    }

    /** @return array<int, object> */
    public function sheets(): array
    {
        $visitas = new HojaVisitasExport($this->registros);

        return [
            new HojaAuditoriaExport($this->registros),
            $visitas,
            new HojaResumenExport($this->registros, $visitas),
        ];
    }
}
