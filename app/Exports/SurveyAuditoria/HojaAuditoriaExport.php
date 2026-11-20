<?php

namespace App\Exports\SurveyAuditoria;

use App\Models\SurveyRegistro;
use App\Services\survey123\AuditoriaSurveyRegistro;
use App\Services\survey123\RequisitosDeFotos;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
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
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Hoja «Auditoría»: una fila por registro; cada variable de la encuesta va seguida de su «Validar ▸ …».
 */
class HojaAuditoriaExport implements FromQuery, WithColumnFormatting, WithColumnWidths, WithCustomValueBinder, WithEvents, WithHeadings, WithMapping, WithStrictNullComparison, WithTitle
{
    use ValoresSeguros;

    public const FORMATO_FECHA = 'yyyy\-mm\-dd\ hh:mm:ss';

    public const ESTADO_OK = 'OK';

    public const ESTADO_ALERTAS = 'Con alertas';

    public const ESTADO_ERRORES = 'Con errores';

    public const ESTADO_SIN_AUDITAR = 'Sin auditar';

    public const IA_SIN_REVISAR = 'Sin revisar';

    public const IA_SIN_OBSERVACIONES = 'Sin observaciones';

    public const IA_CON_OBSERVACIONES = 'Con observaciones';

    /** @var array<int, array{titulo: string, valor: Closure, campos: array<int, string>, ancho: int, fecha?: bool}>|null */
    private ?array $variables = null;

    /** @param Builder<SurveyRegistro> $registros */
    public function __construct(private readonly Builder $registros) {}

    public function title(): string
    {
        return 'Auditoría';
    }

    /** @return Builder<SurveyRegistro> */
    public function query(): Builder
    {
        $foto = fn (string $campo): Closure => fn ($q) => $q->where('campo', $campo);

        return $this->registros->clone()
            ->reorder()
            ->setEagerLoads([])
            ->with('ultimaRevisionIA')
            ->select([
                'survey_registros.id', 'object_id', 'global_id', 'fecha_registro', 'tecnico', 'contrato', 'orden_trabajo',
                'categoria', 'serie_medidor', 'lectura_medidor', 'actividad', 'suspension_ejecutada', 'metodo_suspension',
                'serie_sonda', 'serie_caps', 'motivo_no_ejecucion', 'justificacion', 'latitud', 'longitud',
                'creado_en_origen', 'editado_en_origen', 'estado', 'hallazgos', 'total_errores', 'total_alertas', 'auditado_en',
            ])
            ->withCount([
                'adjuntos as fotos_fachada' => $foto('foto_fachada'),
                'adjuntos as fotos_medidor' => $foto('foto_medidor'),
                'adjuntos as fotos_ivt' => $foto('foto_ivt'),
                'adjuntos as fotos_actividad' => fn ($q) => $q->whereIn('campo', RequisitosDeFotos::CAMPOS_ACTIVIDAD),
            ])
            ->orderBy('survey_registros.fecha_registro')
            ->orderBy('survey_registros.id');
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        $encabezados = [];

        foreach ($this->variables() as $variable) {
            $encabezados[] = $variable['titulo'];
            $encabezados[] = 'Validar ▸ '.$variable['titulo'];
        }

        return array_merge($encabezados, ['Estado de asignación', 'Errores ✖', 'Alertas ⚠', 'Estado de la fila', 'Revisión IA', 'Resumen IA']);
    }

    /** @return array<int, mixed> */
    public function map($registro): array
    {
        $auditado = $registro->auditado_en !== null;
        $hallazgos = $auditado ? ($registro->hallazgos ?? []) : [];
        $fila = [];

        foreach ($this->variables() as $variable) {
            $fila[] = ($variable['valor'])($registro);
            $fila[] = $auditado ? $this->validar($hallazgos, $variable['campos']) : null;
        }

        $fila[] = $registro->estado->label();
        $fila[] = $auditado ? (int) $registro->total_errores : null;
        $fila[] = $auditado ? (int) $registro->total_alertas : null;
        $fila[] = $this->estadoDeLaFila($registro);

        $revision = $registro->ultimaRevisionIA;
        $fila[] = match (true) {
            $revision === null => self::IA_SIN_REVISAR,
            $revision->con_observaciones => self::IA_CON_OBSERVACIONES,
            default => self::IA_SIN_OBSERVACIONES,
        };
        $fila[] = $revision?->veredicto['resumen'] ?? null;

        return $fila;
    }

    /** @return array<string, string> */
    public function columnFormats(): array
    {
        $formatos = [];

        foreach ($this->variables() as $i => $variable) {
            if ($variable['fecha'] ?? false) {
                $formatos[Coordinate::stringFromColumnIndex($i * 2 + 1)] = self::FORMATO_FECHA;
            }
        }

        return $formatos;
    }

    /** @return array<string, int> */
    public function columnWidths(): array
    {
        $anchos = [];

        foreach ($this->variables() as $i => $variable) {
            $anchos[Coordinate::stringFromColumnIndex($i * 2 + 1)] = $variable['ancho'];
            $anchos[Coordinate::stringFromColumnIndex($i * 2 + 2)] = 34;
        }

        $primera = count($this->variables()) * 2 + 1;
        $anchos[Coordinate::stringFromColumnIndex($primera)] = 22;
        $anchos[Coordinate::stringFromColumnIndex($primera + 1)] = 11;
        $anchos[Coordinate::stringFromColumnIndex($primera + 2)] = 11;
        $anchos[Coordinate::stringFromColumnIndex($primera + 3)] = 16;
        $anchos[Coordinate::stringFromColumnIndex($primera + 4)] = 20;
        $anchos[Coordinate::stringFromColumnIndex($primera + 5)] = 70;

        return $anchos;
    }

    /** @return array<string, Closure> */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $evento): void {
                $hoja = $evento->sheet->getDelegate();
                $estadoDeLaFila = count($this->variables()) * 2 + 4;
                $totalColumnas = $estadoDeLaFila + 2;
                $ultima = Coordinate::stringFromColumnIndex($totalColumnas);
                $hayDatos = $hoja->getHighestRow() >= 2;
                $ultimaFila = max($hoja->getHighestRow(), 2);

                foreach ($this->variables() as $i => $variable) {
                    $this->estiloEncabezado($hoja, $i * 2 + 1, EstiloDeInforme::OSCURO);
                    $this->estiloEncabezado($hoja, $i * 2 + 2, '4D7C0F');

                    $validar = Coordinate::stringFromColumnIndex($i * 2 + 2);
                    if ($hayDatos) {
                        $hoja->getStyle("{$validar}2:{$validar}{$ultimaFila}")->getFill()
                            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(EstiloDeInforme::GRIS_CLARO);
                    }
                }

                for ($c = $estadoDeLaFila - 3; $c <= $estadoDeLaFila; $c++) {
                    $this->estiloEncabezado($hoja, $c, '7F1D1D');
                }

                for ($c = $estadoDeLaFila + 1; $c <= $totalColumnas; $c++) {
                    $this->estiloEncabezado($hoja, $c, '0F766E');
                }

                $hoja->getRowDimension(1)->setRowHeight(48);
                $hoja->freezePane('A2');
                $hoja->setAutoFilter("A1:{$ultima}{$ultimaFila}");
                $hoja->getTabColor()->setRGB(EstiloDeInforme::OSCURO);
                EstiloDeInforme::bordes($hoja, "A1:{$ultima}".$hoja->getHighestRow());
                if ($hayDatos) {
                    $hoja->getStyle("A2:{$ultima}{$ultimaFila}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                }
                EstiloDeInforme::prepararImpresion($hoja, ajustarAlAncho: false, filaDeEncabezado: 1);

                if ($hayDatos) {
                    $this->resaltarFilas($hoja, $ultima, $ultimaFila, $estadoDeLaFila);
                    $this->pintarCierreDeFila($hoja, $ultimaFila, $estadoDeLaFila);
                }

                EstiloDeInforme::reforzarTextoSeguro($hoja);
            },
        ];
    }

    /**
     * Valores de `campo` de los hallazgos que alguna columna «Validar ▸» muestra.
     *
     * @return array<int, string>
     */
    public function camposValidados(): array
    {
        return array_values(array_unique(array_merge(...array_column($this->variables(), 'campos'))));
    }

    /**
     * Resultado de «Validar ▸ <variable>»: «OK» si ningún hallazgo guardado apunta a los `campos` de la columna;
     * si hay, «✖ mensaje» por cada error y «⚠ mensaje» por cada alerta, unidos con « | ».
     * Fotos repetidas (`fotos`) se muestran en la columna de fachada; `ubicacion` en x y en y.
     *
     * @param  array<int, array<string, string>>  $hallazgos
     * @param  array<int, string>  $campos
     */
    private function validar(array $hallazgos, array $campos): string
    {
        $mensajes = [];

        foreach ($hallazgos as $hallazgo) {
            if (in_array($hallazgo['campo'] ?? null, $campos, true)) {
                $marca = ($hallazgo['severidad'] ?? null) === AuditoriaSurveyRegistro::ERROR ? '✖' : '⚠';
                $mensajes[] = "{$marca} {$hallazgo['mensaje']}";
            }
        }

        return $mensajes === [] ? 'OK' : implode(' | ', $mensajes);
    }

    /**
     * Además del formato condicional de toda la fila, pinta con color fijo «Errores», «Alertas» y «Estado de la fila»
     * para que el color se vea también en visores que ignoran el formato condicional.
     */
    private function pintarCierreDeFila(mixed $hoja, int $ultimaFila, int $columnaDeEstado): void
    {
        $estado = Coordinate::stringFromColumnIndex($columnaDeEstado);
        $desde = Coordinate::stringFromColumnIndex($columnaDeEstado - 2);

        for ($fila = 2; $fila <= $ultimaFila; $fila++) {
            $color = match ($hoja->getCell("{$estado}{$fila}")->getValue()) {
                self::ESTADO_ERRORES => 'FDE2E2',
                self::ESTADO_ALERTAS => 'FFF1CC',
                default => null,
            };

            if ($color !== null) {
                $hoja->getStyle("{$desde}{$fila}:{$estado}{$fila}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($color);
            }
        }
    }

    private function estadoDeLaFila(SurveyRegistro $registro): string
    {
        return match (true) {
            $registro->auditado_en === null => self::ESTADO_SIN_AUDITAR,
            $registro->total_errores > 0 => self::ESTADO_ERRORES,
            $registro->total_alertas > 0 => self::ESTADO_ALERTAS,
            default => self::ESTADO_OK,
        };
    }

    private function estiloEncabezado(mixed $hoja, int $columna, string $color): void
    {
        $celda = Coordinate::stringFromColumnIndex($columna).'1';

        $estilo = $hoja->getStyle($celda);
        $estilo->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $estilo->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($color);
        $estilo->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
    }

    private function resaltarFilas(mixed $hoja, string $ultima, int $ultimaFila, int $columnaDeEstado): void
    {
        $estado = Coordinate::stringFromColumnIndex($columnaDeEstado);

        $errores = new Conditional;
        $errores->setConditionType(Conditional::CONDITION_EXPRESSION)
            ->addCondition("\${$estado}2=\"".self::ESTADO_ERRORES.'"');
        $errores->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color('FFFDE2E2'))->setEndColor(new Color('FFFDE2E2'));

        $alertas = new Conditional;
        $alertas->setConditionType(Conditional::CONDITION_EXPRESSION)
            ->addCondition("\${$estado}2=\"".self::ESTADO_ALERTAS.'"');
        $alertas->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color('FFFFF1CC'))->setEndColor(new Color('FFFFF1CC'));

        $hoja->getStyle("A2:{$ultima}{$ultimaFila}")->setConditionalStyles([$errores, $alertas]);
    }

    /** @return array<int, array{titulo: string, valor: Closure, campos: array<int, string>, ancho: int, fecha?: bool}> */
    private function variables(): array
    {
        return $this->variables ??= [
            ['titulo' => 'ObjectID', 'valor' => fn ($r) => $r->object_id, 'campos' => [], 'ancho' => 11],
            ['titulo' => 'GlobalID', 'valor' => fn ($r) => $r->global_id, 'campos' => [], 'ancho' => 38],
            ['titulo' => 'Fecha de registro', 'valor' => fn ($r) => $this->fecha($r->fecha_registro), 'campos' => ['fecha_registro'], 'ancho' => 20, 'fecha' => true],
            ['titulo' => 'Nombre del Técnico', 'valor' => fn ($r) => $r->tecnico, 'campos' => ['tecnico'], 'ancho' => 28],
            ['titulo' => 'Contrato', 'valor' => fn ($r) => $this->numeroOTexto($r->contrato), 'campos' => ['contrato'], 'ancho' => 12],
            ['titulo' => 'Orden de Trabajo', 'valor' => fn ($r) => $this->numeroOTexto($r->orden_trabajo), 'campos' => ['orden_trabajo'], 'ancho' => 14],
            ['titulo' => 'Categoría en campo', 'valor' => fn ($r) => $r->categoria, 'campos' => ['categoria'], 'ancho' => 16],
            ['titulo' => 'Serie de medidor instalado', 'valor' => fn ($r) => $this->numeroOTexto($r->serie_medidor), 'campos' => ['serie_medidor'], 'ancho' => 16],
            ['titulo' => 'Lectura del medidor instalado', 'valor' => fn ($r) => $this->numeroOTexto($r->lectura_medidor), 'campos' => ['lectura_medidor'], 'ancho' => 14],
            ['titulo' => '¿Qué actividad va a ejecutar?', 'valor' => fn ($r) => $r->actividad, 'campos' => ['actividad'], 'ancho' => 16],
            ['titulo' => '¿Se pudo ejecutar la suspensión?', 'valor' => fn ($r) => match ($r->suspension_ejecutada) {
                true => 'si', false => 'no', default => null,
            }, 'campos' => ['suspension_ejecutada'], 'ancho' => 14],
            ['titulo' => '¿Método de suspensión instalado?', 'valor' => fn ($r) => $r->metodo_suspension, 'campos' => ['metodo_suspension'], 'ancho' => 24],
            ['titulo' => 'Serie de la sonda utilizada', 'valor' => fn ($r) => $this->numeroOTexto($r->serie_sonda), 'campos' => ['serie_sonda'], 'ancho' => 16],
            ['titulo' => 'Serie del Caps', 'valor' => fn ($r) => $this->numeroOTexto($r->serie_caps), 'campos' => ['serie_caps'], 'ancho' => 16],
            ['titulo' => 'Motivo por el cual no se pudo ejecutar la suspensión', 'valor' => fn ($r) => $r->motivo_no_ejecucion, 'campos' => ['motivo_no_ejecucion'], 'ancho' => 26],
            ['titulo' => 'Fotografías de la fachada (3 fotos)', 'valor' => fn ($r) => (int) $r->fotos_fachada, 'campos' => ['foto_fachada', 'fotos'], 'ancho' => 14],
            ['titulo' => 'Fotografías del medidor', 'valor' => fn ($r) => (int) $r->fotos_medidor, 'campos' => ['foto_medidor'], 'ancho' => 14],
            ['titulo' => 'Fotografía del IVT', 'valor' => fn ($r) => (int) $r->fotos_ivt, 'campos' => ['foto_ivt'], 'ancho' => 14],
            ['titulo' => 'Fotografías de la suspensión, reconexión ejecutada o novedad encontrada', 'valor' => fn ($r) => (int) $r->fotos_actividad, 'campos' => RequisitosDeFotos::CAMPOS_ACTIVIDAD, 'ancho' => 18],
            ['titulo' => 'JUSTIFICACIÓN', 'valor' => fn ($r) => $r->justificacion, 'campos' => ['justificacion'], 'ancho' => 44],
            ['titulo' => 'CreationDate', 'valor' => fn ($r) => $this->fecha($r->creado_en_origen), 'campos' => [], 'ancho' => 20, 'fecha' => true],
            ['titulo' => 'EditDate', 'valor' => fn ($r) => $this->fecha($r->editado_en_origen), 'campos' => [], 'ancho' => 20, 'fecha' => true],
            ['titulo' => 'x', 'valor' => fn ($r) => $r->longitud === null ? null : (float) $r->longitud, 'campos' => ['ubicacion'], 'ancho' => 14],
            ['titulo' => 'y', 'valor' => fn ($r) => $r->latitud === null ? null : (float) $r->latitud, 'campos' => ['ubicacion'], 'ancho' => 14],
        ];
    }

    private function fecha(mixed $fecha): ?float
    {
        return $fecha === null ? null : Date::dateTimeToExcel(
            Carbon::parse($fecha)->setTimezone(config('survey_auditoria.zona_horaria_excel'))->toDateTime()
        );
    }
}
