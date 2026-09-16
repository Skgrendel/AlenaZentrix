<?php

namespace App\Livewire\Informes;

use App\Enums\EstadoAuditoria;
use App\Enums\EstadoOrden;
use App\Enums\TipoServicio;
use App\Models\auditoria;
use App\Models\OrdenJob;
use App\Models\OrigenInformacion;
use App\Models\Proceso;
use App\Models\TipologiaOrden;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.app')]
#[Title('Informes')]
/**
 * Módulo de generación y descarga del informe unificado en Excel.
 * Cada fila contiene la orden, su reporte de campo (si existe) y la auditoría (si existe).
 */
class InformesIndex extends Component
{
    public string $fechaInicio = '';

    public string $fechaFin = '';

    public string $estado = '';

    public string $servicio = '';

    public string $subTipo = '';

    public string $proceso = '';

    public string $origenInformacion = '';

    public bool $previewGenerado = false;

    public array $preview = [];

    public function estadoOptions(): array
    {
        return collect(EstadoOrden::cases())
            ->mapWithKeys(fn ($c) => [$c->value => $c->label()])
            ->all();
    }

    public function servicioOptions(): array
    {
        return collect(TipoServicio::cases())
            ->mapWithKeys(fn (TipoServicio $c) => [$c->value => $c->label()])
            ->all();
    }

    public function subTipoOptions(): array
    {
        return match ($this->servicio) {
            TipoServicio::GestionLegal->value => TipologiaOrden::query()
                ->where('activo', true)
                ->orderBy('nombre')
                ->pluck('nombre', 'id')
                ->toArray(),
            TipoServicio::Investigacion->value => [
                'interna' => 'Interna',
                'contratista' => 'Contratista',
                'Terceros' => 'Terceros',
            ],
            TipoServicio::AuditoriaCampo->value => [
                'visita_calidad_emergencia' => 'Visita de Calidad — Emergencia',
                'visita_calidad_trabajos_varios' => 'Visita de Calidad — Trabajos Varios',
                'visita_calidad_medidores_digitales' => 'Visita de Calidad — Medidores Digitales',
            ],
            default => [],
        };
    }

    public function procesoOptions(): array
    {
        return Proceso::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->pluck('nombre', 'id')
            ->toArray();
    }

    public function origenInformacionOptions(): array
    {
        return OrigenInformacion::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->pluck('nombre', 'id')
            ->toArray();
    }

    public function updatedServicio(): void
    {
        $this->subTipo = '';
        $this->preview = [];
        $this->previewGenerado = false;
    }

    public function generarVista(): void
    {
        $this->preview = $this->buildPreviewData();
        $this->previewGenerado = true;
    }

    public function download(): StreamedResponse
    {
        [$headers, $rows, $filename] = $this->xlsxUnificado();

        return response()->streamDownload(function () use ($headers, $rows) {
            $writer = new Writer;
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValues($headers));
            foreach ($rows as $row) {
                $writer->addRow(Row::fromValues($row));
            }
            $writer->close();
        }, $filename.'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function baseQuery()
    {
        return OrdenJob::query()
            ->with([
                'personal',
                'proceso',
                'origenInformacion',
                'reportes' => fn ($q) => $q->latest()->with([
                    'auditoria',
                    'comercio.tipoComercio',
                    'tipoImposibilidad',
                    'tipoRegulador',
                ]),
            ])
            ->when($this->fechaInicio, fn ($q) => $q->whereDate('created_at', '>=', $this->fechaInicio))
            ->when($this->fechaFin, fn ($q) => $q->whereDate('created_at', '<=', $this->fechaFin))
            ->when($this->estado, fn ($q) => $q->where('estado', $this->estado))
            ->when($this->servicio, fn ($q) => $q->where('tipo_servicio', $this->servicio))
            ->when($this->proceso, fn ($q) => $q->where('proceso_id', $this->proceso))
            ->when($this->origenInformacion, fn ($q) => $q->where('origen_informacion_id', $this->origenInformacion))
            ->when($this->subTipo, function ($q) {
                match ($this->servicio) {
                    TipoServicio::GestionLegal->value => $q->where('tipo_gestion_legal', $this->subTipo),
                    TipoServicio::Investigacion->value => $q->where('tipo_investigacion', $this->subTipo),
                    TipoServicio::AuditoriaCampo->value => $q->where('tipo_auditoria_campo', $this->subTipo),
                    default => null,
                };
            })
            ->orderBy('created_at', 'desc');
    }

    private function mapRow(OrdenJob $o): array
    {
        $reporte = $o->reportes->first();
        $auditoria = $reporte?->auditoria;

        return [
            // Orden
            $o->numero_orden,
            $o->proceso?->nombre ?? '',
            $o->origenInformacion?->nombre ?? '',
            $o->tipo_servicio->label(),
            $o->subtipoLabel(),
            $o->estado->label(),
            match ($o->resultado) {
                'positivo' => 'Positiva',
                'negativo' => 'Negativa',
                default => '',
            },
            $o->prioridad->label(),
            $o->personal ? "{$o->personal->nombres} {$o->personal->apellidos}" : '',
            $o->cliente ?? '',
            $o->contrato ?? '',
            $o->direccion ?? '',
            $o->barrio ?? '',
            $o->medidor ?? '',
            $o->objetivo ?? '',
            $o->numero_noticia_criminal ?? '',
            $o->fecha_asignacion?->format('d/m/Y H:i') ?? '',
            $o->fecha_limite?->format('d/m/Y') ?? '',
            $o->fecha_completado?->format('d/m/Y H:i') ?? '',
            // Reporte del técnico
            $reporte ? 'Sí' : 'No',
            $reporte?->lectura ?? '',
            $reporte?->numero_medidor ?? '',
            $reporte?->comercio?->nombre_comercio ?? '',
            $reporte?->tipoImposibilidad?->nombre ?? '',
            $reporte?->tipoRegulador?->nombre ?? '',
            $reporte ? implode(' | ', array_column($reporte->actividades ?? [], 'actividades')) : '',
            // Columnas específicas del servicio/subtipo filtrado (ver columnasAdicionalesDefinicion())
            ...array_map(fn (array $columna) => ($columna['value'])($reporte), $this->columnasAdicionalesDefinicion()),
            $reporte?->observaciones ?? '',
            $reporte?->conclusion ?? '',
            $reporte?->created_at?->format('d/m/Y H:i') ?? '',
            // Auditoría
            $auditoria instanceof auditoria
                ? ($auditoria->estado instanceof EstadoAuditoria ? $auditoria->estado->label() : ($auditoria->estado ?? ''))
                : 'Sin auditoría',
            $auditoria?->observaciones ?? '',
            $auditoria?->fecha_auditoria?->format('d/m/Y H:i') ?? '',
        ];
    }

    private function xlsxUnificado(): array
    {
        $headers = [
            'N° Orden', 'Proceso', 'Origen de Información', 'Servicio', 'Sub-tipo', 'Estado', 'Resultado', 'Prioridad', 'Investigador',
            'Cliente', 'Contrato', 'Dirección', 'Barrio', 'Medidor', 'Objetivo', 'N° Noticia Criminal',
            'Fecha Asignación', 'Fecha Límite', 'Fecha Completado',
            'Tiene Reporte', 'Lectura', 'N° Medidor Reporte', 'Comercio', 'Imposibilidad',
            'Tipo Regulador', 'Actividades', 'Observaciones Técnico', 'Conclusión', 'Fecha Reporte',
            'Estado Auditoría', 'Observaciones Auditoría', 'Fecha Auditoría',
        ];

        $columnasAdicionales = array_column($this->columnasAdicionalesDefinicion(), 'label');
        array_splice($headers, 26, 0, $columnasAdicionales);

        $rows = $this->baseQuery()
            ->get()
            ->map(fn (OrdenJob $o) => $this->mapRow($o))
            ->all();

        return [$headers, $rows, 'informe-unificado-'.now()->format('Y-m-d')];
    }

    protected function buildPreviewData(): array
    {
        return $this->baseQuery()
            ->limit(50)
            ->get()
            ->map(function (OrdenJob $o) {
                $reporte = $o->reportes->first();
                $auditoria = $reporte?->auditoria;

                return [
                    'N° Orden' => $o->numero_orden,
                    'Proceso' => $o->proceso?->nombre ?? '—',
                    'Origen de Información' => $o->origenInformacion?->nombre ?? '—',
                    'Servicio' => $o->tipo_servicio->label(),
                    'Sub-tipo' => $o->subtipoLabel(),
                    'Estado' => $o->estado->label(),
                    'Resultado' => match ($o->resultado) {
                        'positivo' => 'Positiva',
                        'negativo' => 'Negativa',
                        default => '—',
                    },
                    'Investigador' => $o->personal ? "{$o->personal->nombres} {$o->personal->apellidos}" : '—',
                    'Cliente' => $o->cliente ?? '—',
                    'Tiene Reporte' => $reporte ? 'Sí' : 'No',
                    ...$this->columnasAdicionalesValores($o, $reporte),
                    'Conclusión' => $reporte?->conclusion ?? '—',
                    'Estado Auditoría' => $auditoria instanceof auditoria
                        ? ($auditoria->estado instanceof EstadoAuditoria ? $auditoria->estado->label() : ($auditoria->estado ?? '—'))
                        : 'Sin auditoría',
                    'Fecha Completado' => $o->fecha_completado?->format('d/m/Y') ?? '—',
                ];
            })
            ->all();
    }

    /**
     * Columnas adicionales específicas del servicio/subtipo filtrado, para que la vista previa
     * y el Excel muestren lo realmente auditado (hoy solo cubre Auditoría de Campo; nuevos
     * servicios se agregan extendiendo el match de columnasAdicionalesDefinicion()).
     * Solo se activan cuando el usuario filtra por un servicio concreto, para no ensuciar
     * la vista "Todos los servicios" con columnas que no aplican a la mayoría de filas.
     *
     * @return array<int, array{label: string, value: \Closure(mixed $reporte): string}>
     */
    private function columnasAdicionalesDefinicion(): array
    {
        return match ($this->servicio) {
            TipoServicio::AuditoriaCampo->value => $this->columnasAuditoriaCampo(),
            default => [],
        };
    }

    /** @return array<int, array{label: string, value: \Closure(mixed $reporte): string}> */
    private function columnasAuditoriaCampo(): array
    {
        $yesNo = fn (?bool $v): string => match (true) {
            $v === null => '—',
            $v => 'Sí',
            default => 'No',
        };
        $dato = fn (string $key) => fn ($reporte) => $reporte?->datos_servicio[$key] ?? '—';

        if ($this->subTipo === 'visita_calidad_medidores_digitales') {
            return [
                ['label' => 'Fecha y Hora Atención', 'value' => $dato('fecha_hora_atencion')],
                ['label' => 'Técnico que Atendió', 'value' => $dato('tecnico_atendio_medidor')],
                ['label' => 'Nombre Atiende', 'value' => $dato('nombre_atiende')],
                ['label' => 'N° Medidor Instalado', 'value' => $dato('numero_medidor_instalado')],
                ['label' => 'Teléfono Contacto', 'value' => $dato('celular_atiende')],
                ['label' => 'Resultado Auditoría', 'value' => fn ($reporte) => match ($reporte?->datos_servicio['resultado_medidor'] ?? null) {
                    'conforme' => 'Conforme',
                    'no_conforme' => 'No conforme',
                    'seguimiento_urgente' => 'Requiere seguimiento urgente',
                    default => '—',
                }],
                ['label' => 'Informó Gratuidad', 'value' => fn ($reporte) => $yesNo($reporte?->datos_servicio['informo_gratuidad_medidor'] ?? null)],
                ['label' => 'Solicitó/Recibió Dinero', 'value' => fn ($reporte) => $yesNo($reporte?->datos_servicio['solicito_dinero_medidor'] ?? null)],
                ['label' => 'Gestión Conforme', 'value' => fn ($reporte) => $yesNo($reporte?->datos_servicio['gestion_conforme_medidor'] ?? null)],
                ['label' => 'Requiere Seguimiento', 'value' => fn ($reporte) => $yesNo($reporte?->datos_servicio['requiere_seguimiento_medidor'] ?? null)],
                ['label' => 'Hallazgos', 'value' => $dato('hallazgos_medidor')],
            ];
        }

        if (in_array($this->subTipo, ['visita_calidad_emergencia', 'visita_calidad_trabajos_varios'], true)) {
            return [
                ['label' => 'Recibió Visita', 'value' => fn ($reporte) => $yesNo($reporte?->datos_servicio['recibio_visita'] ?? null)],
                ['label' => 'Se Realizó Revisión', 'value' => fn ($reporte) => $yesNo($reporte?->datos_servicio['se_realizo_revision'] ?? null)],
                ['label' => 'Fecha Ejecución', 'value' => $dato('fecha_ejecucion')],
                ['label' => 'Descripción Trabajo', 'value' => $dato('descripcion_trabajo')],
                ['label' => 'Fuga/Falla Detectada', 'value' => fn ($reporte) => $yesNo($reporte?->datos_servicio['se_detecto_fuga'] ?? null)],
                ['label' => 'Problema Persiste', 'value' => fn ($reporte) => $yesNo($reporte?->datos_servicio['problema_persiste'] ?? null)],
                ['label' => 'Servicio Correcto', 'value' => fn ($reporte) => $yesNo($reporte?->datos_servicio['servicio_correcto'] ?? null)],
                ['label' => 'Se Usaron Materiales', 'value' => fn ($reporte) => $yesNo($reporte?->datos_servicio['se_usaron_materiales'] ?? null)],
                ['label' => 'Usuario Asumió Costo', 'value' => fn ($reporte) => $yesNo($reporte?->datos_servicio['usuario_asumio_costo'] ?? null)],
                ['label' => 'Entregó Factura/IVT', 'value' => fn ($reporte) => $yesNo($reporte?->datos_servicio['entrego_factura'] ?? null)],
            ];
        }

        // Servicio Auditoría de Campo sin subtipo elegido: los subtipos tienen estructuras de
        // datos distintas, así que no mezclamos columnas hasta que el usuario elija uno.
        return [];
    }

    /** @return array<string, string> */
    private function columnasAdicionalesValores(OrdenJob $o, $reporte): array
    {
        return collect($this->columnasAdicionalesDefinicion())
            ->mapWithKeys(fn (array $columna) => [$columna['label'] => ($columna['value'])($reporte)])
            ->all();
    }

    public function render(): View
    {
        return view('livewire.informes.informes-index', [
            'estadoOptions' => $this->estadoOptions(),
            'servicioOptions' => $this->servicioOptions(),
            'subTipoOptions' => $this->subTipoOptions(),
            'procesoOptions' => $this->procesoOptions(),
            'origenInformacionOptions' => $this->origenInformacionOptions(),
        ]);
    }
}
