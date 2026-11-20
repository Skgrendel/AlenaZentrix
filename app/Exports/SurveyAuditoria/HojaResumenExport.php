<?php

namespace App\Exports\SurveyAuditoria;

use App\Enums\ResultadoComparacion;
use App\Models\SurveyRegistro;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Hoja «Resumen»: totales de la auditoría, por técnico, por regla y resultados de las visitas de campo.
 */
class HojaResumenExport implements FromArray, WithColumnWidths, WithCustomValueBinder, WithEvents, WithStrictNullComparison, WithTitle
{
    use ValoresSeguros;

    /** @var array<int, int> filas (base 1) de títulos de sección, para darles estilo */
    private array $titulos = [];

    /** @var array<int, int> filas (base 1) de encabezados de tabla */
    private array $encabezados = [];

    /** @param Builder<SurveyRegistro> $registros */
    public function __construct(
        private readonly Builder $registros,
        private readonly HojaVisitasExport $visitas,
    ) {}

    public function title(): string
    {
        return 'Resumen';
    }

    /** @return array<int, array<int, mixed>> */
    public function array(): array
    {
        $this->titulos = [];
        $this->encabezados = [];
        $filas = [];

        $base = fn () => $this->registros->clone()->reorder()->setEagerLoads([])->toBase();

        $totales = $base()->selectRaw(
            'count(*) as total,
            sum(case when auditado_en is null then 1 else 0 end) as sin_auditar,
            sum(case when total_errores > 0 then 1 else 0 end) as con_errores,
            sum(case when total_alertas > 0 then 1 else 0 end) as con_alertas,
            sum(case when auditado_en is not null and total_errores = 0 and total_alertas = 0 then 1 else 0 end) as ok'
        )->first();

        $filas[] = ['Resumen de la auditoría de suspensiones (Survey123)'];
        $this->titulos[] = count($filas);
        $filas[] = ['Generado el', now()->format('Y-m-d H:i:s')];
        $filas[] = [];

        $filas[] = ['Totales'];
        $this->titulos[] = count($filas);
        $filas[] = ['Registros', (int) $totales->total];
        $filas[] = ['Con errores', (int) $totales->con_errores];
        $filas[] = ['Con alertas', (int) $totales->con_alertas];
        $filas[] = ['OK (auditados sin hallazgos)', (int) $totales->ok];
        $filas[] = ['Sin auditar', (int) $totales->sin_auditar];
        $filas[] = [];

        $filas[] = ['Por técnico'];
        $this->titulos[] = count($filas);
        $filas[] = ['Técnico', 'Registros', 'Con errores', 'Con alertas', 'OK'];
        $this->encabezados[] = count($filas);

        $porTecnico = $base()->selectRaw(
            'tecnico, count(*) as total,
            sum(case when total_errores > 0 then 1 else 0 end) as con_errores,
            sum(case when total_alertas > 0 then 1 else 0 end) as con_alertas,
            sum(case when auditado_en is not null and total_errores = 0 and total_alertas = 0 then 1 else 0 end) as ok'
        )->groupBy('tecnico')->orderByDesc('total')->orderBy('tecnico')->get();

        foreach ($porTecnico as $fila) {
            $filas[] = [$fila->tecnico ?? '(sin técnico)', (int) $fila->total, (int) $fila->con_errores, (int) $fila->con_alertas, (int) $fila->ok];
        }

        $filas[] = [];
        $filas[] = ['Reglas con más hallazgos'];
        $this->titulos[] = count($filas);
        $filas[] = ['Regla', 'Severidad', 'Hallazgos', 'Registros afectados'];
        $this->encabezados[] = count($filas);

        foreach ($this->frecuenciaPorRegla($base) as [$regla, $severidad, $hallazgos, $registros]) {
            $filas[] = [$regla, $severidad, $hallazgos, $registros];
        }

        $filas[] = [];
        $filas[] = ['Resultados de las visitas de auditoría'];
        $this->titulos[] = count($filas);

        $porResultado = $this->visitas->consulta()->reorder()->toBase()
            ->selectRaw('reportes.resultado_comparacion as resultado, count(*) as total')
            ->groupBy('reportes.resultado_comparacion')
            ->pluck('total', 'resultado');

        $visitas = 0;

        foreach (ResultadoComparacion::cases() as $caso) {
            $total = (int) ($porResultado[$caso->value] ?? 0);
            $visitas += $total;
            $filas[] = [$caso->label(), $total];
        }

        $sinResultado = (int) $porResultado->sum() - $visitas;
        $filas[] = [HojaVisitasExport::SIN_RESULTADO, $sinResultado];
        $filas[] = ['Total de visitas', $visitas + $sinResultado];

        return $filas;
    }

    /** @return array<string, int> */
    public function columnWidths(): array
    {
        return ['A' => 48, 'B' => 22, 'C' => 16, 'D' => 20, 'E' => 12];
    }

    /** @return array<string, Closure> */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $evento): void {
                $hoja = $evento->sheet->getDelegate();

                foreach ($this->titulos as $fila) {
                    $estilo = $hoja->getStyle("A{$fila}");
                    $estilo->getFont()->setBold(true)->setSize($fila === 1 ? 14 : 12);
                }

                foreach ($this->encabezados as $fila) {
                    $estilo = $hoja->getStyle("A{$fila}:E{$fila}");
                    $estilo->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                    $estilo->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1F3864');
                }
            },
        ];
    }

    /**
     * @param  Closure(): \Illuminate\Database\Query\Builder  $base
     * @return array<int, array{0: string, 1: string, 2: int, 3: int}>
     */
    private function frecuenciaPorRegla(Closure $base): array
    {
        $conteo = [];

        $base()->select('survey_registros.id', 'survey_registros.hallazgos')
            ->whereNotNull('survey_registros.hallazgos')
            ->orderBy('survey_registros.id')
            ->chunkById(500, function ($registros) use (&$conteo): void {
                foreach ($registros as $registro) {
                    $vistos = [];

                    foreach (json_decode((string) $registro->hallazgos, true) ?: [] as $hallazgo) {
                        $clave = ($hallazgo['regla'] ?? '?').'|'.($hallazgo['severidad'] ?? '?');
                        $conteo[$clave]['hallazgos'] = ($conteo[$clave]['hallazgos'] ?? 0) + 1;
                        $vistos[$clave] = true;
                    }

                    foreach (array_keys($vistos) as $clave) {
                        $conteo[$clave]['registros'] = ($conteo[$clave]['registros'] ?? 0) + 1;
                    }
                }
            }, 'survey_registros.id', 'id');

        uasort($conteo, fn (array $a, array $b): int => $b['hallazgos'] <=> $a['hallazgos']);

        $filas = [];

        foreach ($conteo as $clave => $datos) {
            [$regla, $severidad] = explode('|', $clave);
            $filas[] = [$regla, $severidad === 'error' ? 'Error' : 'Alerta', $datos['hallazgos'], $datos['registros']];
        }

        return $filas;
    }
}
