<?php

namespace App\Livewire\Informes;

use App\Enums\EstadoAuditoria;
use App\Enums\EstadoOrden;
use App\Models\auditoria;
use App\Models\OrdenJob;
use App\Models\reportes as Reporte;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.app')]
#[Title('Informes')]
class InformesIndex extends Component
{
    public string $tipoInforme = 'ordenes';

    public string $fechaInicio = '';

    public string $fechaFin = '';

    public string $estado = '';

    public bool $previewGenerado = false;

    public array $preview = [];

    public function mount(): void
    {
        $this->tipoInforme = $this->defaultTipo();
    }

    public function tiposDisponibles(): array
    {
        $user = auth()->user();
        $tipos = [];

        if ($user->can('informes.ordenes')) {
            $tipos['ordenes'] = 'Órdenes Asignadas';
        }
        if ($user->can('informes.tecnico')) {
            $tipos['reportes'] = 'Respuestas del Técnico';
        }
        if ($user->can('informes.auditorias')) {
            $tipos['auditorias'] = 'Respuestas del Auditor';
        }

        return $tipos;
    }

    public function puedeSeleccionarTipo(): bool
    {
        return count($this->tiposDisponibles()) > 1;
    }

    private function defaultTipo(): string
    {
        return array_key_first($this->tiposDisponibles()) ?? 'ordenes';
    }

    public function tipoEfectivo(): string
    {
        $disponibles = $this->tiposDisponibles();

        if (isset($disponibles[$this->tipoInforme])) {
            return $this->tipoInforme;
        }

        return array_key_first($disponibles) ?? 'ordenes';
    }

    public function estadoOptions(): array
    {
        if ($this->tipoEfectivo() === 'auditorias') {
            return collect(EstadoAuditoria::cases())
                ->mapWithKeys(fn ($c) => [$c->value => $c->label()])
                ->all();
        }

        return collect(EstadoOrden::cases())
            ->mapWithKeys(fn ($c) => [$c->value => $c->label()])
            ->all();
    }

    public function updatedTipoInforme(): void
    {
        $this->estado = '';
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
        $tipo = $this->tipoEfectivo();
        $user = auth()->user();

        [$headers, $rows, $filename] = match ($tipo) {
            'ordenes' => $this->xlsxOrdenes(),
            'reportes' => $this->xlsxReportes($user),
            'auditorias' => $this->xlsxAuditorias(),
        };

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

    private function xlsxOrdenes(): array
    {
        $rows = OrdenJob::query()
            ->with('personal')
            ->when($this->fechaInicio, fn ($q) => $q->whereDate('created_at', '>=', $this->fechaInicio))
            ->when($this->fechaFin, fn ($q) => $q->whereDate('created_at', '<=', $this->fechaFin))
            ->when($this->estado, fn ($q) => $q->where('estado', $this->estado))
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (OrdenJob $o) => [
                $o->numero_orden,
                $o->estado->label(),
                $o->cliente,
                $o->contrato,
                $o->direccion,
                $o->barrio,
                $o->medidor,
                $o->prioridad->label(),
                $o->personal ? "{$o->personal->nombres} {$o->personal->apellidos}" : '',
                $o->fecha_asignacion?->format('d/m/Y H:i') ?? '',
                $o->fecha_limite?->format('d/m/Y') ?? '',
                $o->fecha_completado?->format('d/m/Y H:i') ?? '',
            ])
            ->all();

        return [
            ['N° Orden', 'Estado', 'Cliente', 'Contrato', 'Dirección', 'Barrio', 'Medidor', 'Prioridad', 'Técnico', 'Fecha Asignación', 'Fecha Límite', 'Fecha Completado'],
            $rows,
            'ordenes-asignadas-'.now()->format('Y-m-d'),
        ];
    }

    private function xlsxReportes($user): array
    {
        $rows = Reporte::query()
            ->with(['ordenJob.personal', 'comercio.tipoComercio', 'tipoImposibilidad', 'tipoRegulador'])
            ->when(! $user->can('ordenes.ver'), fn ($q) => $q->whereHas(
                'ordenJob', fn ($q2) => $q2->where('personals_id', $user->personals_id)
            ))
            ->when($this->fechaInicio, fn ($q) => $q->whereDate('created_at', '>=', $this->fechaInicio))
            ->when($this->fechaFin, fn ($q) => $q->whereDate('created_at', '<=', $this->fechaFin))
            ->when($this->estado, fn ($q) => $q->whereHas(
                'ordenJob', fn ($q2) => $q2->where('estado', $this->estado)
            ))
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (Reporte $r) => [
                $r->ordenJob?->numero_orden ?? '',
                $r->ordenJob?->estado?->label() ?? '',
                $r->ordenJob?->cliente ?? '',
                $r->ordenJob?->contrato ?? '',
                $r->ordenJob?->direccion ?? '',
                $r->ordenJob?->barrio ?? '',
                $r->ordenJob?->personal ? "{$r->ordenJob->personal->nombres} {$r->ordenJob->personal->apellidos}" : '',
                $r->lectura ?? '',
                $r->numero_medidor ?? '',
                $r->comercio?->nombre_comercio ?? '',
                $r->comercio?->tipoComercio?->nombre ?? '',
                $r->tipoImposibilidad?->nombre ?? '',
                $r->tipoRegulador?->nombre ?? '',
                $r->cau ?? '',
                $r->observaciones ?? '',
                $r->created_at?->format('d/m/Y H:i') ?? '',
            ])
            ->all();

        return [
            ['N° Orden', 'Estado Orden', 'Cliente', 'Contrato', 'Dirección', 'Barrio', 'Técnico', 'Lectura', 'N° Medidor', 'Comercio', 'Tipo Comercio', 'Imposibilidad', 'Tipo Regulador', 'CAU', 'Observaciones', 'Fecha Reporte'],
            $rows,
            'respuestas-tecnico-'.now()->format('Y-m-d'),
        ];
    }

    private function xlsxAuditorias(): array
    {
        $rows = auditoria::query()
            ->with(['reporte.ordenJob.personal'])
            ->when($this->fechaInicio, fn ($q) => $q->whereDate('fecha_auditoria', '>=', $this->fechaInicio))
            ->when($this->fechaFin, fn ($q) => $q->whereDate('fecha_auditoria', '<=', $this->fechaFin))
            ->when($this->estado, fn ($q) => $q->where('estado', $this->estado))
            ->orderBy('fecha_auditoria', 'desc')
            ->get()
            ->map(fn (auditoria $a) => [
                $a->reporte?->ordenJob?->numero_orden ?? '',
                $a->reporte?->ordenJob?->estado?->label() ?? '',
                $a->reporte?->ordenJob?->cliente ?? '',
                $a->reporte?->ordenJob?->contrato ?? '',
                $a->reporte?->ordenJob?->medidor ?? '',
                $a->reporte?->ordenJob?->barrio ?? '',
                $a->reporte?->ordenJob?->personal
                    ? "{$a->reporte->ordenJob->personal->nombres} {$a->reporte->ordenJob->personal->apellidos}"
                    : '',
                $a->estado instanceof EstadoAuditoria ? $a->estado->label() : ($a->estado ?? ''),
                $a->observaciones ?? '',
                $a->reporte?->ordenJob?->fecha_completado?->format('d/m/Y H:i') ?? '',
                $a->fecha_auditoria?->format('d/m/Y H:i') ?? '',
            ])
            ->all();

        return [
            ['N° Orden', 'Estado Orden', 'Cliente', 'Contrato', 'Medidor', 'Barrio', 'Técnico', 'Estado Auditoría', 'Observaciones', 'Fecha Completado Orden', 'Fecha Auditoría'],
            $rows,
            'respuestas-auditor-'.now()->format('Y-m-d'),
        ];
    }

    protected function buildPreviewData(): array
    {
        $tipo = $this->tipoEfectivo();
        $user = auth()->user();

        if ($tipo === 'ordenes') {
            return OrdenJob::query()
                ->with('personal')
                ->when($this->fechaInicio, fn ($q) => $q->whereDate('created_at', '>=', $this->fechaInicio))
                ->when($this->fechaFin, fn ($q) => $q->whereDate('created_at', '<=', $this->fechaFin))
                ->when($this->estado, fn ($q) => $q->where('estado', $this->estado))
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get()
                ->map(fn (OrdenJob $o) => [
                    'N° Orden' => $o->numero_orden,
                    'Estado' => $o->estado->label(),
                    'Cliente' => $o->cliente,
                    'Contrato' => $o->contrato,
                    'Medidor' => $o->medidor,
                    'Barrio' => $o->barrio,
                    'Prioridad' => $o->prioridad->label(),
                    'Técnico' => $o->personal ? "{$o->personal->nombres} {$o->personal->apellidos}" : '—',
                    'Fecha Límite' => $o->fecha_limite?->format('d/m/Y') ?? '—',
                    'Fecha Completado' => $o->fecha_completado?->format('d/m/Y') ?? '—',
                ])
                ->all();
        }

        if ($tipo === 'reportes') {
            return Reporte::query()
                ->with(['ordenJob.personal', 'comercio.tipoComercio', 'tipoImposibilidad', 'tipoRegulador'])
                ->when(! $user->can('ordenes.ver'), fn ($q) => $q->whereHas(
                    'ordenJob', fn ($q2) => $q2->where('personals_id', $user->personals_id)
                ))
                ->when($this->fechaInicio, fn ($q) => $q->whereDate('created_at', '>=', $this->fechaInicio))
                ->when($this->fechaFin, fn ($q) => $q->whereDate('created_at', '<=', $this->fechaFin))
                ->when($this->estado, fn ($q) => $q->whereHas(
                    'ordenJob', fn ($q2) => $q2->where('estado', $this->estado)
                ))
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get()
                ->map(fn (Reporte $r) => [
                    'N° Orden' => $r->ordenJob?->numero_orden ?? '—',
                    'Estado Orden' => $r->ordenJob?->estado?->label() ?? '—',
                    'Cliente' => $r->ordenJob?->cliente ?? '—',
                    'Contrato' => $r->ordenJob?->contrato ?? '—',
                    'N° Medidor' => $r->numero_medidor ?? '—',
                    'Barrio' => $r->ordenJob?->barrio ?? '—',
                    'Técnico' => $r->ordenJob?->personal
                        ? "{$r->ordenJob->personal->nombres} {$r->ordenJob->personal->apellidos}"
                        : '—',
                    'Lectura' => $r->lectura ?? '—',
                    'Comercio' => $r->comercio?->nombre_comercio ?? '—',
                    'Imposibilidad' => $r->tipoImposibilidad?->nombre ?? '—',
                    'CAU' => $r->cau ?? '—',
                    'Fecha Reporte' => $r->created_at?->format('d/m/Y H:i') ?? '—',
                ])
                ->all();
        }

        if ($tipo === 'auditorias') {
            return auditoria::query()
                ->with(['reporte.ordenJob.personal'])
                ->when($this->fechaInicio, fn ($q) => $q->whereDate('fecha_auditoria', '>=', $this->fechaInicio))
                ->when($this->fechaFin, fn ($q) => $q->whereDate('fecha_auditoria', '<=', $this->fechaFin))
                ->when($this->estado, fn ($q) => $q->where('estado', $this->estado))
                ->orderBy('fecha_auditoria', 'desc')
                ->limit(50)
                ->get()
                ->map(fn (auditoria $a) => [
                    'N° Orden' => $a->reporte?->ordenJob?->numero_orden ?? '—',
                    'Estado Orden' => $a->reporte?->ordenJob?->estado?->label() ?? '—',
                    'Cliente' => $a->reporte?->ordenJob?->cliente ?? '—',
                    'Contrato' => $a->reporte?->ordenJob?->contrato ?? '—',
                    'Medidor' => $a->reporte?->ordenJob?->medidor ?? '—',
                    'Barrio' => $a->reporte?->ordenJob?->barrio ?? '—',
                    'Técnico' => $a->reporte?->ordenJob?->personal
                        ? "{$a->reporte->ordenJob->personal->nombres} {$a->reporte->ordenJob->personal->apellidos}"
                        : '—',
                    'Estado Auditoría' => $a->estado instanceof EstadoAuditoria ? $a->estado->label() : ($a->estado ?? '—'),
                    'Observaciones' => $a->observaciones ?? '—',
                    'Fecha Auditoría' => $a->fecha_auditoria?->format('d/m/Y H:i') ?? '—',
                ])
                ->all();
        }

        return [];
    }

    public function render(): View
    {
        return view('livewire.informes.informes-index', [
            'puedeSeleccionarTipo' => $this->puedeSeleccionarTipo(),
            'tiposDisponibles' => $this->tiposDisponibles(),
            'estadoOptions' => $this->estadoOptions(),
            'tipoEfectivo' => $this->tipoEfectivo(),
        ]);
    }
}
