<?php

namespace App\Exports\SurveyAuditoria;

use App\Enums\ResultadoComparacion;
use App\Models\reportes;
use App\Models\SurveyRegistro;
use App\Services\survey123\ComparacionAuditoriaSuspension;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Hoja «Visitas de auditoría»: un reporte del auditor de campo por fila, contrastado con lo reportado en Survey123.
 */
class HojaVisitasExport implements FromQuery, WithColumnFormatting, WithColumnWidths, WithCustomValueBinder, WithEvents, WithHeadings, WithMapping, WithStrictNullComparison, WithTitle
{
    use ValoresSeguros;

    private const FIJAS = ['OT original', 'Contrato', 'Orden de auditoría', 'Auditor', 'Fecha de la visita', 'Resultado final'];

    /** Campos que compara ComparacionAuditoriaSuspension, en el orden en que se muestran. */
    public const CAMPOS = [
        'ejecucion' => '¿Suspensión ejecutada?',
        'metodo' => 'Método de suspensión',
        'serie_medidor' => 'Serie del medidor',
        'lectura' => 'Lectura del medidor',
        'ubicacion' => 'Ubicación de la visita',
        'categoria' => 'Categoría',
        'serie_sonda' => 'Serie de la sonda',
        'serie_caps' => 'Serie del Caps',
    ];

    public const SIN_RESULTADO = 'Sin resultado';

    /** @param Builder<SurveyRegistro> $registros */
    public function __construct(private readonly Builder $registros) {}

    public function title(): string
    {
        return 'Visitas de auditoría';
    }

    /** Reportes de auditores vinculados a los registros exportados (sin orden ni columnas). */
    public function consulta(): Builder
    {
        $ids = $this->registros->clone()->reorder()->setEagerLoads([])->select('survey_registros.id');

        return reportes::query()
            ->withoutGlobalScopes()
            ->join('orden_jobs', 'orden_jobs.id', '=', 'reportes.orden_job_id')
            ->join('survey_registros', 'survey_registros.id', '=', 'orden_jobs.survey_registro_id')
            ->leftJoin('personals', 'personals.id', '=', 'orden_jobs.personals_id')
            ->whereIn('orden_jobs.survey_registro_id', $ids);
    }

    /** @return Builder<reportes> */
    public function query(): Builder
    {
        return $this->consulta()
            ->select([
                'reportes.id', 'reportes.created_at', 'reportes.comparacion', 'reportes.resultado_comparacion',
                'orden_jobs.numero_orden as orden_auditoria',
                'survey_registros.orden_trabajo as ot_original',
                'survey_registros.contrato as contrato_original',
                'personals.nombres as auditor_nombres', 'personals.apellidos as auditor_apellidos',
            ])
            ->orderBy('reportes.created_at')
            ->orderBy('reportes.id');
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        $encabezados = self::FIJAS;

        foreach (self::CAMPOS as $etiqueta) {
            array_push($encabezados, "{$etiqueta} (reportado)", "{$etiqueta} (encontrado)", "Validar ▸ {$etiqueta}");
        }

        $encabezados[] = 'Alertas (campos no críticos)';

        return $encabezados;
    }

    /** @return array<int, mixed> */
    public function map($reporte): array
    {
        $comparacion = $reporte->comparacion ?? [];
        $filas = collect($comparacion['filas'] ?? [])->keyBy('campo');
        $auditor = trim("{$reporte->auditor_nombres} {$reporte->auditor_apellidos}");

        $fila = [
            $this->numeroOTexto($reporte->ot_original),
            $this->numeroOTexto($reporte->contrato_original),
            $reporte->orden_auditoria,
            $auditor === '' ? null : $auditor,
            $reporte->created_at === null ? null : Date::dateTimeToExcel($reporte->created_at),
            ResultadoComparacion::tryFrom((string) $reporte->resultado_comparacion)?->label() ?? self::SIN_RESULTADO,
        ];

        foreach (array_keys(self::CAMPOS) as $campo) {
            $dato = $filas->get($campo);

            array_push($fila, $dato['reportado'] ?? null, $dato['encontrado'] ?? null, $this->validar($dato['resultado'] ?? null));
        }

        $fila[] = $comparacion['alertas'] ?? null;

        return $fila;
    }

    /** @return array<string, string> */
    public function columnFormats(): array
    {
        return ['E' => HojaAuditoriaExport::FORMATO_FECHA];
    }

    /** @return array<string, int> */
    public function columnWidths(): array
    {
        $anchos = ['A' => 14, 'B' => 12, 'C' => 18, 'D' => 28, 'E' => 20, 'F' => 16];

        foreach (array_keys(self::CAMPOS) as $i => $campo) {
            $base = count(self::FIJAS) + $i * 3 + 1;
            $anchos[Coordinate::stringFromColumnIndex($base)] = 24;
            $anchos[Coordinate::stringFromColumnIndex($base + 1)] = 24;
            $anchos[Coordinate::stringFromColumnIndex($base + 2)] = 18;
        }

        $anchos[Coordinate::stringFromColumnIndex(count(self::FIJAS) + count(self::CAMPOS) * 3 + 1)] = 14;

        return $anchos;
    }

    /** @return array<string, Closure> */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $evento): void {
                $hoja = $evento->sheet->getDelegate();
                $total = count(self::FIJAS) + count(self::CAMPOS) * 3 + 1;
                $ultima = Coordinate::stringFromColumnIndex($total);
                $hayDatos = $hoja->getHighestRow() >= 2;
                $ultimaFila = max($hoja->getHighestRow(), 2);

                for ($c = 1; $c <= $total; $c++) {
                    $esValidar = $c > count(self::FIJAS) && $c < $total && ($c - count(self::FIJAS)) % 3 === 0;
                    $color = $c <= count(self::FIJAS) ? '1F3864' : ($c === $total ? '7F1D1D' : ($esValidar ? '2E7D32' : '1F3864'));

                    $estilo = $hoja->getStyle(Coordinate::stringFromColumnIndex($c).'1');
                    $estilo->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                    $estilo->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($color);
                    $estilo->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
                }

                $hoja->getRowDimension(1)->setRowHeight(48);
                $hoja->freezePane('A2');
                $hoja->setAutoFilter("A1:{$ultima}{$ultimaFila}");

                $noConforme = $this->condicion('F', ResultadoComparacion::NoConforme->label(), 'FDE2E2');
                $noVerificable = $this->condicion('F', ResultadoComparacion::NoVerificable->label(), 'FFF1CC');

                if ($hayDatos) {
                    $hoja->getStyle("A2:{$ultima}{$ultimaFila}")->setConditionalStyles([$noConforme, $noVerificable]);
                }
            },
        ];
    }

    private function condicion(string $columna, string $valor, string $color): Conditional
    {
        $condicion = new Conditional;
        $condicion->setConditionType(Conditional::CONDITION_EXPRESSION)->addCondition("\${$columna}2=\"{$valor}\"");
        $condicion->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($color);
        $condicion->getStyle()->getFill()->getEndColor()->setRGB($color);

        return $condicion;
    }

    private function validar(?string $resultado): ?string
    {
        return match ($resultado) {
            ComparacionAuditoriaSuspension::COINCIDE => 'Coincide',
            ComparacionAuditoriaSuspension::NO_COINCIDE => '✖ No coincide',
            ComparacionAuditoriaSuspension::SIN_DATO => '⚠ Sin dato',
            default => null,
        };
    }
}
