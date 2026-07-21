<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Filtro de rango de fechas para el dashboard. Al aplicar o limpiar, emite un
 * evento de navegador que los widgets del dashboard escuchan para recalcular
 * sus datos según la fecha de creación (`created_at`) de las órdenes.
 */
class DashboardFiltroFechas extends Component
{
    public ?string $desde = null;

    public ?string $hasta = null;

    public function aplicar(): void
    {
        $this->dispatch('dashboard-fecha-filtrada', desde: $this->desde, hasta: $this->hasta);
    }

    public function limpiar(): void
    {
        $this->desde = null;
        $this->hasta = null;

        $this->dispatch('dashboard-fecha-filtrada', desde: null, hasta: null);
    }

    public function render(): View
    {
        return view('livewire.dashboard-filtro-fechas');
    }
}
