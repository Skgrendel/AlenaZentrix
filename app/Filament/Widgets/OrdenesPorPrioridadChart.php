<?php

namespace App\Filament\Widgets;

use App\Enums\PrioridadOrden;
use App\Filament\Widgets\Concerns\FiltraPorDashboard;
use App\Models\OrdenJob;
use Filament\Widgets\ChartWidget;

/**
 * Gráfico de dona que muestra la distribución de órdenes por prioridad.
 * Solo incluye en el gráfico las prioridades con al menos una orden registrada.
 */
class OrdenesPorPrioridadChart extends ChartWidget
{
    use FiltraPorDashboard;

    protected ?string $heading = 'Por Prioridad';

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '240px';

    protected function getData(): array
    {
        $counts = $this->aplicarFiltrosOrdenJob(OrdenJob::query())
            ->selectRaw('prioridad, count(*) as total')
            ->groupBy('prioridad')
            ->pluck('total', 'prioridad');

        $colorMap = [
            'baja' => 'rgba(107,114,128,0.8)',
            'media' => 'rgba(59,130,246,0.8)',
            'alta' => 'rgba(245,158,11,0.8)',
            'urgente' => 'rgba(239,68,68,0.8)',
        ];

        $labels = [];
        $data = [];
        $bgColors = [];

        foreach (PrioridadOrden::cases() as $prioridad) {
            $count = $counts[$prioridad->value] ?? 0;
            if ($count > 0) {
                $labels[] = $prioridad->label();
                $data[] = $count;
                $bgColors[] = $colorMap[$prioridad->value] ?? 'rgba(107,114,128,0.8)';
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
