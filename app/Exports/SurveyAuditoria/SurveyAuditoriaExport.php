<?php

namespace App\Exports\SurveyAuditoria;

use App\Models\SurveyRegistro;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Libro de la auditoría de suspensiones de Survey123: Informe (portada), Auditoría, Visitas de auditoría y Resumen.
 *
 * Recibe la consulta de registros ya acotada por empresa y filtros; las hojas se derivan de ella.
 * El contexto (empresa, quién lo genera y los filtros usados) solo se usa para la portada.
 */
class SurveyAuditoriaExport implements WithMultipleSheets
{
    use Exportable;

    public const TIPO_MIME = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    /**
     * @param  Builder<SurveyRegistro>  $registros
     * @param  array{empresa?: ?string, generado_por?: ?string, filtros?: array<int, string>}  $contexto
     */
    public function __construct(private readonly Builder $registros, private readonly array $contexto = []) {}

    /** @param  array{empresa?: ?string, generado_por?: ?string, filtros?: array<int, string>}  $contexto */
    public static function conFiltros(FiltrosSurveyExportacion $filtros, array $contexto = []): self
    {
        return new self($filtros->query(), $contexto + ['filtros' => $filtros->descripcion()]);
    }

    /**
     * PhpSpreadsheet guarda las celdas en memoria (unos 150 MB por cada 5.000 registros):
     * se sube el tope solo durante la exportación y no se limita el tiempo.
     */
    public static function ampliarRecursos(): void
    {
        $limite = ini_get('memory_limit');

        if ($limite !== '-1' && (int) $limite < 768 && str_ends_with(strtoupper((string) $limite), 'M')) {
            ini_set('memory_limit', '768M');
        }

        set_time_limit(0);
    }

    public static function nombreDeArchivo(): string
    {
        return 'auditoria-suspensiones-'.now()->format('Ymd-His').'.xlsx';
    }

    /** @return array<int, object> */
    public function sheets(): array
    {
        $visitas = new HojaVisitasExport($this->registros);
        $resumen = new HojaResumenExport($this->registros, $visitas);

        return [
            new HojaInformeExport($this->registros, $visitas, $resumen, $this->contexto),
            new HojaAuditoriaExport($this->registros),
            $visitas,
            $resumen,
        ];
    }
}
