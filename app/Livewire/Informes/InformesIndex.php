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
                    'Estado' => $o->estado->label(),
                    'Resultado' => match ($o->resultado) {
                        'positivo' => 'Positiva',
                        'negativo' => 'Negativa',
                        default => '—',
                    },
                    'Investigador' => $o->personal ? "{$o->personal->nombres} {$o->personal->apellidos}" : '—',
                    'Cliente' => $o->cliente ?? '—',
                    'Tiene Reporte' => $reporte ? 'Sí' : 'No',
                    'Conclusión' => $reporte?->conclusion ?? '—',
                    'Estado Auditoría' => $auditoria instanceof auditoria
                        ? ($auditoria->estado instanceof EstadoAuditoria ? $auditoria->estado->label() : ($auditoria->estado ?? '—'))
                        : 'Sin auditoría',
                    'Fecha Completado' => $o->fecha_completado?->format('d/m/Y') ?? '—',
                ];
            })
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
