<?php

namespace App\Filament\Widgets\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;

/**
 * Estado y aplicación de los filtros del panel (fechas + criterios de orden/auditoría)
 * que los widgets del dashboard reciben vía el evento `dashboard-filtros-aplicados`
 * disparado por App\Livewire\DashboardFiltros.
 */
trait FiltraPorDashboard
{
    public ?string $desde = null;

    public ?string $hasta = null;

    public ?string $estado = null;

    public ?string $prioridad = null;

    public ?string $tipoServicio = null;

    public ?string $personalsId = null;

    public ?string $resultado = null;

    public ?string $estadoAuditoria = null;

    public ?string $tipoAnomaliaId = null;

    public ?string $procesoId = null;

    #[On('dashboard-filtros-aplicados')]
    public function actualizarFiltrosDashboard(
        ?string $desde = null,
        ?string $hasta = null,
        ?string $estado = null,
        ?string $prioridad = null,
        ?string $tipoServicio = null,
        ?string $personalsId = null,
        ?string $resultado = null,
        ?string $estadoAuditoria = null,
        ?string $tipoAnomaliaId = null,
        ?string $procesoId = null,
    ): void {
        $this->desde = $desde;
        $this->hasta = $hasta;
        $this->estado = $estado;
        $this->prioridad = $prioridad;
        $this->tipoServicio = $tipoServicio;
        $this->personalsId = $personalsId;
        $this->resultado = $resultado;
        $this->estadoAuditoria = $estadoAuditoria;
        $this->tipoAnomaliaId = $tipoAnomaliaId;
        $this->procesoId = $procesoId;
    }

    /** Aplica los criterios de selección (sin fechas) a una consulta de OrdenJob. */
    protected function aplicarCriteriosOrdenJob(Builder $query): Builder
    {
        return $query
            ->when($this->estado, fn ($q, $v) => $q->where('estado', $v))
            ->when($this->prioridad, fn ($q, $v) => $q->where('prioridad', $v))
            ->when($this->tipoServicio, fn ($q, $v) => $q->where('tipo_servicio', $v))
            ->when($this->personalsId, fn ($q, $v) => $q->where('personals_id', $v))
            ->when($this->resultado, fn ($q, $v) => $q->where('resultado', $v))
            ->when($this->procesoId, fn ($q, $v) => $q->where('proceso_id', $v))
            ->when($this->estadoAuditoria, fn ($q, $v) => $q->whereHas(
                'reportes.auditoria', fn ($qq) => $qq->where('estado', $v)
            ))
            ->when($this->tipoAnomaliaId, fn ($q, $v) => $q->whereHas(
                'reportes.auditoria', fn ($qq) => $qq->where('tipo_anomalia_id', $v)
            ));
    }

    /** Aplica fechas (sobre `created_at`) y criterios de selección a una consulta de OrdenJob. */
    protected function aplicarFiltrosOrdenJob(Builder $query): Builder
    {
        return $this->aplicarCriteriosOrdenJob($query)
            ->when($this->desde, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($this->hasta, fn ($q, $v) => $q->whereDate('created_at', '<=', $v));
    }
}
