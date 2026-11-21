<?php

namespace App\Exports\SurveyAuditoria;

use App\Enums\EstadoSurveyRegistro;
use App\Models\AjusteAuditoria;
use App\Models\SurveyRegistro;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCharts;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Hoja «Informe»: portada del libro con el logo, los filtros usados, las cifras principales,
 * una gráfica y los hallazgos más frecuentes. Es la hoja que se entrega y se imprime.
 */
class HojaInformeExport implements FromArray, WithCharts, WithColumnWidths, WithCustomValueBinder, WithDrawings, WithEvents, WithStrictNullComparison, WithTitle
{
    use ValoresSeguros;

    private const COLUMNAS = 12;

    /** @var array<int, array{fila: int, columna: int, color: string}> tarjetas de cifras (fila de la etiqueta) */
    private array $tarjetas = [];

    /** @var array<int, int> filas de títulos de sección */
    private array $secciones = [];

    /** @var array<int, array{0: int, 1: int}> filas de encabezado de tabla [fila, última columna] */
    private array $encabezados = [];

    /** @var array<int, array{0: int, 1: int, 2: int}> rangos de tabla con bordes [filaInicial, filaFinal, últimaColumna] */
    private array $tablas = [];

    /** @var array<int, array{0: int, 1: int}> filas de texto corrido [fila, altura] */
    private array $notas = [];

    /** @var array<string, array{0: int, 1: int}> merges de ancho completo por fila: [colInicial, colFinal] */
    private array $combinadas = [];

    private int $filaCategorias = 0;

    private int $ultimaFilaCategorias = 0;

    private int $filaDeLaGrafica = 0;

    /**
     * @param  Builder<SurveyRegistro>  $registros
     * @param  array{empresa?: ?string, generado_por?: ?string, filtros?: array<int, string>}  $contexto
     */
    public function __construct(
        private readonly Builder $registros,
        private readonly HojaVisitasExport $visitas,
        private readonly HojaResumenExport $resumen,
        private readonly array $contexto = [],
    ) {}

    public function title(): string
    {
        return 'Informe';
    }

    /** @return array<string, int> */
    public function columnWidths(): array
    {
        return array_fill_keys(range('A', 'L'), 13);
    }

    /** @return array<int, array<int, mixed>> */
    public function array(): array
    {
        $this->tarjetas = $this->secciones = $this->encabezados = $this->tablas = $this->notas = $this->combinadas = [];

        $filas = [];
        $agregar = function (array $fila = []) use (&$filas): int {
            $filas[] = array_pad($fila, self::COLUMNAS, null);

            return count($filas);
        };

        $zona = config('survey_auditoria.zona_horaria_excel');
        $generado = now()->timezone($zona)->format('d/m/Y h:i a');
        $por = $this->contexto['generado_por'] ?? null;

        // Encabezado: el logo ocupa A1:B3 y el texto va a su derecha.
        $this->combinadas[$agregar([null, null, EstiloDeInforme::TITULO_DEL_INFORME])] = [3, self::COLUMNAS];
        $this->combinadas[$agregar([null, null, $this->contexto['empresa'] ?? 'Alena Zentrix'])] = [3, self::COLUMNAS];
        $this->combinadas[$agregar([null, null, "Generado el {$generado} (hora de Colombia)".($por ? " · por {$por}" : '')])] = [3, self::COLUMNAS];
        $agregar(); // franja de colores
        $agregar();

        $this->seccion($agregar(['Filtros aplicados']));
        $filtros = $this->contexto['filtros'] ?? [];

        foreach ($filtros === [] ? ['Sin filtros: todos los registros de la empresa.'] : $filtros as $linea) {
            $fila = $agregar([$linea]);
            $this->combinadas[$fila] = [1, self::COLUMNAS];
        }

        $agregar();

        $totales = $this->registros->clone()->reorder()->setEagerLoads([])->toBase()->selectRaw(
            'count(*) as total,
            sum(case when auditado_en is null then 1 else 0 end) as sin_auditar,
            sum(case when total_errores > 0 then 1 else 0 end) as con_errores,
            sum(case when total_alertas > 0 then 1 else 0 end) as con_alertas,
            sum(case when auditado_en is not null and total_errores = 0 and total_alertas = 0 then 1 else 0 end) as ok,
            sum(case when suspension_ejecutada = 1 then 1 else 0 end) as ejecutadas'
        )->first();

        $total = (int) $totales->total;

        $this->seccion($agregar(['Resultado de las reglas de auditoría']));
        $this->tarjetasEn($agregar, [
            ['Registros', $total, EstiloDeInforme::OSCURO],
            ['Con errores', (int) $totales->con_errores, EstiloDeInforme::ROJO],
            ['Con alertas', (int) $totales->con_alertas, 'B7791F'],
            ['OK, sin hallazgos', (int) $totales->ok, '3F7D20'],
            ['Sin auditar', (int) $totales->sin_auditar, EstiloDeInforme::GRIS_TEXTO],
            ['Suspensión ejecutada', (int) $totales->ejecutadas, EstiloDeInforme::OSCURO],
        ]);
        $agregar();

        $porEstado = $this->registros->clone()->reorder()->setEagerLoads([])->toBase()
            ->selectRaw('estado, count(*) as total')->groupBy('estado')->pluck('total', 'estado');

        $visitas = $this->visitas->consulta()->reorder()->toBase()->count();

        $this->seccion($agregar(['Asignación a campo y revisión con IA']));
        $this->tarjetasEn($agregar, [
            ['Pendientes por asignar', (int) ($porEstado[EstadoSurveyRegistro::Pendiente->value] ?? 0), 'B7791F'],
            ['Asignados a campo', (int) ($porEstado[EstadoSurveyRegistro::Asignado->value] ?? 0), '3F7D20'],
            ['Visitas de auditoría', (int) $visitas, EstiloDeInforme::OSCURO],
            ...$this->tarjetasDeIA($total),
        ]);
        $agregar();

        // Tabla y gráfica del resultado de las reglas.
        $this->seccion($agregar(['Distribución de los registros']));
        $encabezado = $agregar(['Categoría', 'Registros', '%']);
        $this->encabezados[] = [$encabezado, 3];
        $this->filaCategorias = $encabezado + 1;

        foreach ([
            ['Con errores', (int) $totales->con_errores],
            ['Con alertas', (int) $totales->con_alertas],
            ['OK', (int) $totales->ok],
            ['Sin auditar', (int) $totales->sin_auditar],
        ] as [$nombre, $cantidad]) {
            $agregar([$nombre, $cantidad, $total > 0 ? $cantidad / $total : 0]);
        }

        $this->ultimaFilaCategorias = count($filas);
        $this->tablas[] = [$encabezado, $this->ultimaFilaCategorias, 3];
        $this->filaDeLaGrafica = $encabezado;

        while (count($filas) < $encabezado + 9) {
            $agregar();
        }

        $agregar();

        // Reglas con más hallazgos.
        $this->seccion($agregar(['Reglas con más hallazgos']));
        $encabezado = $agregar(['Regla', null, null, null, null, null, 'Severidad', null, 'Hallazgos', null, 'Registros afectados']);
        $this->encabezados[] = [$encabezado, self::COLUMNAS];
        $this->combinadas[$encabezado] = [1, 6];
        $reglas = $this->resumen->reglasConMasHallazgos(8);

        foreach ($reglas as [$regla, $severidad, $hallazgos, $afectados]) {
            $fila = $agregar([(AjusteAuditoria::REGLAS[$regla]['nombre'] ?? null) ?? ucfirst(str_replace('_', ' ', (string) $regla)), null, null, null, null, null, $severidad, null, $hallazgos, null, $afectados]);
            $this->combinadas[$fila] = [1, 6];
        }

        if ($reglas === []) {
            $fila = $agregar(['Ningún registro tiene hallazgos.']);
            $this->combinadas[$fila] = [1, self::COLUMNAS];
        }

        $this->tablas[] = [$encabezado, count($filas), self::COLUMNAS];
        $agregar();

        // Cómo leer el libro.
        $this->seccion($agregar(['Cómo leer este libro']));

        foreach ([
            'Auditoría: una fila por registro, con el formato de Libro1. Cada columna «Validar ▸» dice OK o el hallazgo de esa variable.',
            'Una fila roja tiene errores y una amarilla tiene alertas; la última columna resume el estado de la fila.',
            'Visitas de auditoría: lo que reportó el técnico frente a lo que encontró el auditor en campo, variable por variable.',
            'Revisión IA: sugerencia automática sobre las fotos. No cambia el resultado de la auditoría; la decisión es de una persona.',
            'Conclusión: propuesta automática según los hallazgos (sin hallazgos, se aprueba; solo alertas, se aprueba solicitando correcciones; con errores, no se aprueba). Se puede cambiar con la lista de cada fila.',
            'Resumen: cifras por técnico, por regla y por resultado de las visitas. Todas las fechas están en hora de Colombia.',
        ] as $nota) {
            $fila = $agregar([$nota]);
            $this->combinadas[$fila] = [1, self::COLUMNAS];
            $this->notas[] = [$fila, 18];
        }

        return $filas;
    }

    /** @return array<int, Drawing> */
    public function drawings(): array
    {
        $ruta = EstiloDeInforme::rutaDelLogo();

        if ($ruta === null) {
            return [];
        }

        $logo = new Drawing;
        $logo->setName('Logo Alena Zentrix');
        $logo->setPath($ruta);
        $logo->setCoordinates('A1');
        $logo->setHeight(84);
        $logo->setOffsetX(10);
        $logo->setOffsetY(2);

        return [$logo];
    }

    /** @return array<int, Chart> */
    public function charts(): array
    {
        if ($this->filaCategorias === 0) {
            return [];
        }

        $hoja = "'Informe'!";
        $primera = $this->filaCategorias;
        $ultima = $this->ultimaFilaCategorias;

        $categorias = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "{$hoja}\$A\${$primera}:\$A\${$ultima}", null, $ultima - $primera + 1)];
        $valores = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "{$hoja}\$B\${$primera}:\$B\${$ultima}", null, $ultima - $primera + 1)];
        $valores[0]->setFillColor([EstiloDeInforme::ROJO, EstiloDeInforme::AMARILLO, EstiloDeInforme::VERDE, '9AA3AF']);

        $serie = new DataSeries(
            DataSeries::TYPE_BARCHART,
            DataSeries::GROUPING_CLUSTERED,
            [0],
            [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "{$hoja}\$B\$".($primera - 1), null, 1)],
            $categorias,
            $valores,
        );
        $serie->setPlotDirection(DataSeries::DIRECTION_COL);

        $grafica = new Chart(
            'distribucion',
            new Title('Registros según el resultado de las reglas'),
            new Legend(Legend::POSITION_BOTTOM, null, false),
            new PlotArea(null, [$serie]),
        );

        $grafica->setTopLeftPosition('E'.$this->filaDeLaGrafica);
        $grafica->setBottomRightPosition('L'.($this->filaDeLaGrafica + 8));

        return [$grafica];
    }

    /** @return array<string, Closure> */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $evento): void {
                $hoja = $evento->sheet->getDelegate();
                $this->dibujar($hoja);
            },
        ];
    }

    private function dibujar(Worksheet $hoja): void
    {
        $hoja->setShowGridlines(false);
        $hoja->setSelectedCell('A1');
        $hoja->getTabColor()->setRGB(EstiloDeInforme::VERDE);
        $hoja->getParent()->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

        // Encabezado.
        $hoja->getRowDimension(1)->setRowHeight(30);
        $hoja->getRowDimension(2)->setRowHeight(20);
        $hoja->getRowDimension(3)->setRowHeight(20);
        $hoja->getRowDimension(4)->setRowHeight(6);
        $hoja->getStyle('C1')->getFont()->setBold(true)->setSize(20)->getColor()->setRGB(EstiloDeInforme::OSCURO);
        $hoja->getStyle('C2')->getFont()->setBold(true)->setSize(12)->getColor()->setRGB(EstiloDeInforme::GRIS_TEXTO);
        $hoja->getStyle('C3')->getFont()->setSize(10)->getColor()->setRGB(EstiloDeInforme::GRIS_TEXTO);
        $hoja->getStyle('C1:C3')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        foreach ([['A4:C4', EstiloDeInforme::VERDE], ['D4:F4', EstiloDeInforme::AMARILLO], ['G4:I4', EstiloDeInforme::ROJO], ['J4:L4', EstiloDeInforme::OSCURO]] as [$rango, $color]) {
            $hoja->getStyle($rango)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($color);
        }

        foreach ($this->combinadas as $fila => [$desde, $hasta]) {
            $hoja->mergeCells([$desde, $fila, $hasta, $fila]);
        }

        // Secciones.
        foreach ($this->secciones as $fila) {
            $hoja->getRowDimension($fila)->setRowHeight(22);
            $estilo = $hoja->getStyle("A{$fila}:L{$fila}");
            $estilo->getFont()->setBold(true)->setSize(12)->getColor()->setRGB(EstiloDeInforme::OSCURO);
            $estilo->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $estilo->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB(EstiloDeInforme::VERDE);
        }

        // Tarjetas de cifras.
        foreach ($this->tarjetas as ['fila' => $fila, 'columna' => $columna, 'color' => $color]) {
            $this->tarjeta($hoja, $fila, $columna, $color);
        }

        // Tablas.
        foreach ($this->encabezados as [$fila, $ultimaColumna]) {
            $rango = 'A'.$fila.':'.$this->letra($ultimaColumna).$fila;
            $estilo = $hoja->getStyle($rango);
            $estilo->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
            $estilo->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(EstiloDeInforme::OSCURO);
            $estilo->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            $hoja->getRowDimension($fila)->setRowHeight(20);
        }

        foreach ($this->tablas as [$desde, $hasta, $ultimaColumna]) {
            EstiloDeInforme::bordes($hoja, "A{$desde}:".$this->letra($ultimaColumna).$hasta);
            $hoja->getStyle('B'.($desde + 1).':C'.$hasta)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        $hoja->getStyle("C{$this->filaCategorias}:C{$this->ultimaFilaCategorias}")->getNumberFormat()->setFormatCode('0.0%');

        // Tabla de reglas: severidad, hallazgos y registros se centran.
        foreach ($this->tablas as $i => [$desde, $hasta]) {
            if ($i === 1) {
                $hoja->getStyle("G{$desde}:L{$hasta}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
        }

        foreach ($this->notas as [$fila, $altura]) {
            $hoja->getRowDimension($fila)->setRowHeight($altura);
            $hoja->getStyle("A{$fila}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
            $hoja->getStyle("A{$fila}")->getFont()->getColor()->setRGB(EstiloDeInforme::GRIS_TEXTO);
        }

        EstiloDeInforme::prepararImpresion($hoja, ajustarAlAncho: true);
        EstiloDeInforme::reforzarTextoSeguro($hoja);
    }

    private function tarjeta(Worksheet $hoja, int $fila, int $columna, string $color): void
    {
        $desde = $this->letra($columna);
        $hasta = $this->letra($columna + 1);

        $hoja->mergeCells("{$desde}{$fila}:{$hasta}{$fila}");
        $hoja->mergeCells("{$desde}".($fila + 1).":{$hasta}".($fila + 1));
        $hoja->getRowDimension($fila)->setRowHeight(22);
        $hoja->getRowDimension($fila + 1)->setRowHeight(38);

        $rango = "{$desde}{$fila}:{$hasta}".($fila + 1);
        $hoja->getStyle($rango)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(EstiloDeInforme::GRIS_CLARO);
        $hoja->getStyle($rango)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        EstiloDeInforme::bordes($hoja, $rango);

        $hoja->getStyle("{$desde}{$fila}")->getFont()->setSize(9)->getColor()->setRGB(EstiloDeInforme::GRIS_TEXTO);
        $valor = $hoja->getStyle("{$desde}".($fila + 1));
        $valor->getFont()->setBold(true)->setSize(24)->getColor()->setRGB($color);
        $valor->getNumberFormat()->setFormatCode('#,##0');
    }

    /**
     * Agrega al informe una fila de etiquetas y otra de cifras para hasta 6 tarjetas de 2 columnas cada una.
     *
     * @param  array<int, array{0: string, 1: int, 2: string}>  $tarjetas
     */
    private function tarjetasEn(Closure $agregar, array $tarjetas): void
    {
        $etiquetas = [];
        $cifras = [];

        foreach ($tarjetas as $i => [$etiqueta, $cifra]) {
            $etiquetas[$i * 2] = $etiqueta;
            $cifras[$i * 2] = $cifra;
        }

        $fila = $agregar($this->expandir($etiquetas));
        $agregar($this->expandir($cifras));

        foreach ($tarjetas as $i => [, , $color]) {
            $this->tarjetas[] = ['fila' => $fila, 'columna' => $i * 2 + 1, 'color' => $color];
        }
    }

    /**
     * @param  array<int, mixed>  $porIndice
     * @return array<int, mixed>
     */
    private function expandir(array $porIndice): array
    {
        $fila = array_fill(0, self::COLUMNAS, null);

        foreach ($porIndice as $indice => $valor) {
            $fila[$indice] = $valor;
        }

        return $fila;
    }

    /** @return array<int, array{0: string, 1: int, 2: string}> */
    private function tarjetasDeIA(int $total): array
    {
        $base = fn () => $this->registros->clone()->reorder()->setEagerLoads([]);

        $conRevision = $base()->whereHas('revisionesIA')->count();
        $conObservaciones = $base()->whereHas('ultimaRevisionIA', fn ($q) => $q->where('con_observaciones', true))->count();
        $sinObservaciones = $base()->whereHas('ultimaRevisionIA', fn ($q) => $q->where('con_observaciones', false))->count();

        return [
            ['IA: sin revisar', $total - $conRevision, EstiloDeInforme::GRIS_TEXTO],
            ['IA: sin observaciones', $sinObservaciones, '3F7D20'],
            ['IA: con observaciones', $conObservaciones, 'B7791F'],
        ];
    }

    private function seccion(int $fila): void
    {
        $this->secciones[] = $fila;
    }

    private function letra(int $columna): string
    {
        return Coordinate::stringFromColumnIndex($columna);
    }
}
