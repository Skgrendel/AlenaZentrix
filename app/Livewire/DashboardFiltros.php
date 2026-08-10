<?php

namespace App\Livewire;

use App\Enums\EstadoAuditoria;
use App\Enums\EstadoOrden;
use App\Enums\PrioridadOrden;
use App\Enums\TipoServicio;
use App\Models\personals;
use App\Models\Proceso;
use App\Models\TipoAnomalia;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Barra de filtros del panel. Al aplicar o limpiar, emite un evento de navegador
 * (`dashboard-filtros-aplicados`) que los widgets del dashboard escuchan para
 * recalcular sus datos según los criterios seleccionados.
 */
class DashboardFiltros extends Component
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

    /** Solo coordinadores/administradores ven los criterios de selección adicionales a las fechas. */
    public function puedeFiltrarPorCriterios(): bool
    {
        return auth()->user()?->can('ordenes.ver') ?? false;
    }

    /** @return array<string, string> */
    #[Computed]
    public function estados(): array
    {
        return collect(EstadoOrden::cases())->mapWithKeys(fn (EstadoOrden $c) => [$c->value => $c->label()])->all();
    }

    /** @return array<string, string> */
    #[Computed]
    public function prioridades(): array
    {
        return collect(PrioridadOrden::cases())->mapWithKeys(fn (PrioridadOrden $c) => [$c->value => $c->label()])->all();
    }

    /** @return array<string, string> */
    #[Computed]
    public function servicios(): array
    {
        return collect(TipoServicio::cases())->mapWithKeys(fn (TipoServicio $c) => [$c->value => $c->label()])->all();
    }

    /** @return array<string, string> */
    #[Computed]
    public function estadosAuditoria(): array
    {
        return collect(EstadoAuditoria::cases())->mapWithKeys(fn (EstadoAuditoria $c) => [$c->value => $c->label()])->all();
    }

    /** @return array<string, string> */
    #[Computed]
    public function tiposAnomalia(): array
    {
        return TipoAnomalia::query()->where('activo', true)->orderBy('nombre')->pluck('nombre', 'id')->all();
    }

    /** @return array<string, string> */
    #[Computed]
    public function procesos(): array
    {
        return Proceso::query()->orderBy('nombre')->pluck('nombre', 'id')->all();
    }

    /** @return array<string, string> */
    #[Computed]
    public function personales(): array
    {
        return personals::query()
            ->orderBy('nombres')
            ->get(['id', 'nombres', 'apellidos'])
            ->mapWithKeys(fn (personals $p) => [$p->id => "{$p->nombres} {$p->apellidos}"])
            ->all();
    }

    public function aplicar(): void
    {
        $this->dispatch(
            'dashboard-filtros-aplicados',
            desde: $this->desde,
            hasta: $this->hasta,
            estado: $this->estado,
            prioridad: $this->prioridad,
            tipoServicio: $this->tipoServicio,
            personalsId: $this->personalsId,
            resultado: $this->resultado,
            estadoAuditoria: $this->estadoAuditoria,
            tipoAnomaliaId: $this->tipoAnomaliaId,
            procesoId: $this->procesoId,
        );
    }

    public function limpiar(): void
    {
        $this->desde = null;
        $this->hasta = null;
        $this->estado = null;
        $this->prioridad = null;
        $this->tipoServicio = null;
        $this->personalsId = null;
        $this->resultado = null;
        $this->estadoAuditoria = null;
        $this->tipoAnomaliaId = null;
        $this->procesoId = null;

        $this->aplicar();
    }

    public function render(): View
    {
        return view('livewire.dashboard-filtros');
    }
}
