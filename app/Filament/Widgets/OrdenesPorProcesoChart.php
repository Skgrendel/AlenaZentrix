<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\FiltraPorDashboard;
use App\Models\OrdenJob;
use App\Models\Proceso;
use Filament\Widgets\ChartWidget;

/**
 * Gráfico de barras con la distribución de órdenes por proceso interno.
 * Solo incluye procesos con al menos una orden registrada.
 */
class OrdenesPorProcesoChart extends ChartWidget
{
    use FiltraPorDashboard;

    protected ?string $heading = 'Por Proceso';

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '280px';

    protected function getData(): array
    {
        $counts = $this->aplicarFiltrosOrdenJob(OrdenJob::query())
            ->whereNotNull('proceso_id')
            ->selectRaw('proceso_id, count(*) as total')
            ->groupBy('proceso_id')
            ->orderByDesc('total')
            ->pluck('total', 'proceso_id');

        $nombres = Proceso::whereIn('id', $counts->keys())->pluck('nombre', 'id');

        $labels = [];
        $data = [];

        foreach ($counts as $procesoId => $total) {
            $labels[] = $nombres[$procesoId] ?? '—';
            $data[] = $total;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Órdenes',
                    'data' => $data,
                    'backgroundColor' => 'rgba(59,130,246,0.7)',
                    'borderColor' => 'rgb(59,130,246)',
                    'borderWidth' => 1,
                    'borderRadius' => 4,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => ['stepSize' => 1, 'precision' => 0],
                ],
            ],
        ];
    }
}
