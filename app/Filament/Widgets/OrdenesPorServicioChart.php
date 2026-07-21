<?php

namespace App\Filament\Widgets;

use App\Enums\TipoServicio;
use App\Models\OrdenJob;
use Filament\Widgets\ChartWidget;
use Livewire\Attributes\On;

/**
 * Gráfico de dona que muestra la distribución de órdenes por tipo de servicio.
 * Solo incluye en el gráfico los servicios con al menos una orden registrada.
 */
class OrdenesPorServicioChart extends ChartWidget
{
    protected ?string $heading = 'Por Tipo de Servicio';

    protected ?string $pollingInterval = null;

    public ?string $desde = null;

    public ?string $hasta = null;

    #[On('dashboard-fecha-filtrada')]
    public function actualizarFiltroFecha(?string $desde, ?string $hasta): void
    {
        $this->desde = $desde;
        $this->hasta = $hasta;
    }

    protected function getData(): array
    {
        $counts = OrdenJob::query()
            ->when($this->desde, fn ($q, $desde) => $q->whereDate('created_at', '>=', $desde))
            ->when($this->hasta, fn ($q, $hasta) => $q->whereDate('created_at', '<=', $hasta))
            ->selectRaw('tipo_servicio, count(*) as total')
            ->groupBy('tipo_servicio')
            ->pluck('total', 'tipo_servicio');

        $colorMap = [
            'fraudes' => 'rgba(239,68,68,0.8)',
            'investigacion' => 'rgba(245,158,11,0.8)',
            'gestion_legal' => 'rgba(59,130,246,0.8)',
            'vigilancia' => 'rgba(16,185,129,0.8)',
            'Gestión de Riesgo' => 'rgba(99,102,241,0.8)',
        ];

        $labels = [];
        $data = [];
        $bgColors = [];

        foreach (TipoServicio::cases() as $tipo) {
            $count = $counts[$tipo->value] ?? 0;
            if ($count > 0) {
                $labels[] = $tipo->label();
                $data[] = $count;
                $bgColors[] = $colorMap[$tipo->value] ?? 'rgba(107,114,128,0.8)';
            }
        }

        return [
            'datasets' => [
                [
                    'data' => $data,
                    'backgroundColor' => $bgColors,
                    'hoverOffset' => 6,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                ],
            ],
            'cutout' => '65%',
        ];
    }
}
