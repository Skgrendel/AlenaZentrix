<?php

namespace App\Livewire\Informes;

use App\Enums\EstadoAuditoria;
use App\Enums\EstadoOrden;
use App\Enums\TipoServicio;
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
/**
 * Módulo de generación y descarga de informes en Excel.
 * Los tipos disponibles (órdenes, reportes, auditorías) se filtran por los permisos del usuario.
 */
class InformesIndex extends Component
{
    public string $tipoInforme = 'ordenes';

    public string $fechaInicio = '';

    public string $fechaFin = '';

    public string $estado = '';

    public string $servicio = '';

    public string $subTipo = '';

    public bool $previewGenerado = false;

    public array $preview = [];

    public function mount(): void
    {
        $this->tipoInforme = $this->defaultTipo();
    }

    /**
     * Retorna solo los tipos de informe que el usuario autenticado tiene permiso de ver.
     *
     * @return array<string, string>
     */
    public function tiposDisponibles(): array
    {
        $user = auth()->user();
        $tipos = [];

        if ($user->can('informes.ordenes')) {
            $tipos['ordenes'] = 'Órdenes de Trabajo';
        }
        if ($user->can('informes.tecnico')) {
            $tipos['reportes'] = 'Reportes de Campo';
        }
        if ($user->can('informes.auditorias')) {
            $tipos['auditorias'] = 'Auditorías de Calidad';
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

    /**
     * Valida que el tipo seleccionado sea uno disponible para el usuario.
     * Si no lo es, devuelve el primero disponible para evitar acceso indebido.
     */
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

    public function servicioOptions(): array
    {
        return collect(TipoServicio::cases())
            ->mapWithKeys(fn (TipoServicio $c) => [$c->value => $c->label()])
            ->all();
    }

    public function subTipoOptions(): array
    {
        return match ($this->servicio) {
            TipoServicio::GestionLegal->value => [
                'capturas' => 'Capturas Criminales',
                'bandas' => 'Desarticulación de Bandas',
                'querella' => 'Querella Penal',
                'seguimiento_denuncia' => 'Seguimiento a Denuncia',
                'diligencia_judicial' => 'Diligencia Judicial',
            ],
            TipoServicio::Investigacion->value => [
                'interna' => 'Interna',
                'contratista' => 'Contratista',
                'Terceros' => 'Terceros',
            ],
            default => [],
        };
    }

    public function updatedTipoInforme(): void
    {
        $this->estado = '';
        $this->servicio = '';
        $this->subTipo = '';
        $this->preview = [];
        $this->previewGenerado = false;
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

    /**
     * Genera y descarga el archivo Excel correspondiente al tipo de informe activo.
     * Usa OpenSpout para escribir el archivo directamente a la salida sin cargar en memoria.
     */
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
            ->when($this->servicio, fn ($q) => $q->where('tipo_servicio', $this->servicio))
            ->when($this->subTipo, function ($q) {
                match ($this->servicio) {
                    TipoServicio::GestionLegal->value => $q->where('tipo_gestion_legal', $this->subTipo),
                    TipoServicio::Investigacion->value => $q->where('tipo_investigacion', $this->subTipo),
                    default => null,
                };
            })
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (OrdenJob $o) => [
                $o->numero_orden,
                $o->tipo_servicio->label(),
                $o->subtipoLabel(),
                $o->estado->label(),
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
            ])
            ->all();

        return [
            ['N° Orden', 'Servicio', 'Sub-tipo', 'Estado', 'Prioridad', 'Investigador', 'Cliente', 'Contrato', 'Dirección', 'Barrio', 'Medidor', 'Objetivo', 'N° Noticia Criminal', 'Fecha Asignación', 'Fecha Límite', 'Fecha Completado'],
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
            ->when($this->servicio, fn ($q) => $q->whereHas(
                'ordenJob', fn ($q2) => $q2->where('tipo_servicio', $this->servicio)
            ))
            ->when($this->subTipo, fn ($q) => $q->whereHas('ordenJob', function ($inner) {
                match ($this->servicio) {
                    TipoServicio::GestionLegal->value => $inner->where('tipo_gestion_legal', $this->subTipo),
                    TipoServicio::Investigacion->value => $inner->where('tipo_investigacion', $this->subTipo),
                    default => null,
                };
            }))
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (Reporte $r) => [
                $r->ordenJob?->numero_orden ?? '',
                $r->ordenJob?->tipo_servicio?->label() ?? '',
                $r->ordenJob?->subtipoLabel() ?? '—',
                $r->ordenJob?->estado?->label() ?? '',
                $r->ordenJob?->personal ? "{$r->ordenJob->personal->nombres} {$r->ordenJob->personal->apellidos}" : '',
                $r->ordenJob?->direccion ?? '',
                $r->ordenJob?->barrio ?? '',
                $r->ordenJob?->cliente ?? '',
                $r->ordenJob?->contrato ?? '',
                $r->lectura ?? '',
                $r->numero_medidor ?? '',
                $r->comercio?->nombre_comercio ?? '',
                $r->tipoImposibilidad?->nombre ?? '',
                $r->tipoRegulador?->nombre ?? '',
                $r->cau ?? '',
                implode(' | ', array_column($r->actividades ?? [], 'actividades')),
                $r->observaciones ?? '',
                $r->conclusion ?? '',
                $r->created_at?->format('d/m/Y H:i') ?? '',
            ])
            ->all();

        return [
            ['N° Orden', 'Servicio', 'Sub-tipo', 'Estado Orden', 'Investigador', 'Dirección', 'Barrio', 'Cliente', 'Contrato', 'Lectura', 'N° Medidor', 'Comercio', 'Imposibilidad', 'Tipo Regulador', 'CAU', 'Actividades', 'Observaciones', 'Conclusión', 'Fecha Reporte'],
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
            ->when($this->servicio, fn ($q) => $q->whereHas(
                'reporte.ordenJob', fn ($q2) => $q2->where('tipo_servicio', $this->servicio)
            ))
            ->when($this->subTipo, fn ($q) => $q->whereHas('reporte.ordenJob', function ($inner) {
                match ($this->servicio) {
                    TipoServicio::GestionLegal->value => $inner->where('tipo_gestion_legal', $this->subTipo),
                    TipoServicio::Investigacion->value => $inner->where('tipo_investigacion', $this->subTipo),
                    default => null,
                };
            }))
            ->orderBy('fecha_auditoria', 'desc')
            ->get()
            ->map(fn (auditoria $a) => [
                $a->reporte?->ordenJob?->numero_orden ?? '',
                $a->reporte?->ordenJob?->tipo_servicio?->label() ?? '',
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
            ['N° Orden', 'Servicio', 'Estado Orden', 'Cliente', 'Contrato', 'Medidor', 'Barrio', 'Investigador', 'Estado Auditoría', 'Observaciones', 'Fecha Completado Orden', 'Fecha Auditoría'],
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
                ->when($this->servicio, fn ($q) => $q->where('tipo_servicio', $this->servicio))
                ->when($this->subTipo, function ($q) {
                    match ($this->servicio) {
                        TipoServicio::GestionLegal->value => $q->where('tipo_gestion_legal', $this->subTipo),
                        TipoServicio::Investigacion->value => $q->where('tipo_investigacion', $this->subTipo),
                        default => null,
                    };
                })
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get()
                ->map(fn (OrdenJob $o) => [
                    'N° Orden' => $o->numero_orden,
                    'Servicio' => $o->tipo_servicio->label(),
                    'Sub-tipo' => $o->subtipoLabel(),
                    'Estado' => $o->estado->label(),
                    'Prioridad' => $o->prioridad->label(),
                    'Investigador' => $o->personal ? "{$o->personal->nombres} {$o->personal->apellidos}" : '—',
                    'Cliente' => $o->cliente ?? '—',
                    'Objetivo' => $o->objetivo ?? '—',
                    'N° Noticia Criminal' => $o->numero_noticia_criminal ?? '—',
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
                ->when($this->servicio, fn ($q) => $q->whereHas(
                    'ordenJob', fn ($q2) => $q2->where('tipo_servicio', $this->servicio)
                ))
                ->when($this->subTipo, fn ($q) => $q->whereHas('ordenJob', function ($inner) {
                    match ($this->servicio) {
                        TipoServicio::GestionLegal->value => $inner->where('tipo_gestion_legal', $this->subTipo),
                        TipoServicio::Investigacion->value => $inner->where('tipo_investigacion', $this->subTipo),
                        default => null,
                    };
                }))
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get()
                ->map(fn (Reporte $r) => [
                    'N° Orden' => $r->ordenJob?->numero_orden ?? '—',
                    'Servicio' => $r->ordenJob?->tipo_servicio?->label() ?? '—',
                    'Sub-tipo' => $r->ordenJob?->subtipoLabel() ?? '—',
                    'Estado Orden' => $r->ordenJob?->estado?->label() ?? '—',
                    'Investigador' => $r->ordenJob?->personal
                        ? "{$r->ordenJob->personal->nombres} {$r->ordenJob->personal->apellidos}"
                        : '—',
                    'Cliente' => $r->ordenJob?->cliente ?? '—',
                    'Barrio' => $r->ordenJob?->barrio ?? '—',
                    'Actividades' => implode(' | ', array_column($r->actividades ?? [], 'actividades')) ?: '—',
                    'Observaciones' => $r->observaciones ?? '—',
                    'Conclusión' => $r->conclusion ?? '—',
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
                ->when($this->servicio, fn ($q) => $q->whereHas(
                    'reporte.ordenJob', fn ($q2) => $q2->where('tipo_servicio', $this->servicio)
                ))
                ->when($this->subTipo, fn ($q) => $q->whereHas('reporte.ordenJob', function ($inner) {
                    match ($this->servicio) {
                        TipoServicio::GestionLegal->value => $inner->where('tipo_gestion_legal', $this->subTipo),
                        TipoServicio::Investigacion->value => $inner->where('tipo_investigacion', $this->subTipo),
                        default => null,
                    };
                }))
                ->orderBy('fecha_auditoria', 'desc')
                ->limit(50)
                ->get()
                ->map(fn (auditoria $a) => [
                    'N° Orden' => $a->reporte?->ordenJob?->numero_orden ?? '—',
                    'Servicio' => $a->reporte?->ordenJob?->tipo_servicio?->label() ?? '—',
                    'Estado Orden' => $a->reporte?->ordenJob?->estado?->label() ?? '—',
                    'Cliente' => $a->reporte?->ordenJob?->cliente ?? '—',
                    'Contrato' => $a->reporte?->ordenJob?->contrato ?? '—',
                    'Medidor' => $a->reporte?->ordenJob?->medidor ?? '—',
                    'Barrio' => $a->reporte?->ordenJob?->barrio ?? '—',
                    'Investigador' => $a->reporte?->ordenJob?->personal
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
            'servicioOptions' => $this->servicioOptions(),
            'subTipoOptions' => $this->subTipoOptions(),
            'tipoEfectivo' => $this->tipoEfectivo(),
        ]);
    }
}
