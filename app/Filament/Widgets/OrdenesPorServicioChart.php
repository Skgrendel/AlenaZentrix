<?php

namespace App\Filament\Widgets;

use App\Enums\TipoServicio;
use App\Filament\Widgets\Concerns\FiltraPorDashboard;
use App\Models\OrdenJob;
use Filament\Widgets\ChartWidget;

/**
 * Gráfico de dona que muestra la distribución de órdenes por tipo de servicio.
 * Solo incluye en el gráfico los servicios con al menos una orden registrada.
 */
class OrdenesPorServicioChart extends ChartWidget
{
    use FiltraPorDashboard;

    protected ?string $heading = 'Por Tipo de Servicio';

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '240px';

    protected function getData(): array
    {
        $counts = $this->aplicarFiltrosOrdenJob(OrdenJob::query())
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
