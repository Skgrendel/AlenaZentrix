<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\FiltraPorDashboard;
use App\Models\OrdenJob;
use Filament\Widgets\ChartWidget;

/**
 * Gráfico de dona que muestra la distribución de órdenes completadas por resultado
 * (positivo/negativo) frente a las que aún no tienen un resultado registrado.
 */
class OrdenesPorResultadoChart extends ChartWidget
{
    use FiltraPorDashboard;

    protected ?string $heading = 'Por Resultado';

    protected ?string $pollingInterval = null;

    protected function getData(): array
    {
        $counts = $this->aplicarFiltrosOrdenJob(OrdenJob::query())
            ->selectRaw('resultado, count(*) as total')
            ->groupBy('resultado')
            ->pluck('total', 'resultado');

        $opciones = [
            'positivo' => ['label' => 'Positiva', 'color' => 'rgba(16,185,129,0.8)'],
            'negativo' => ['label' => 'Negativa', 'color' => 'rgba(239,68,68,0.8)'],
        ];

        $labels = [];
        $data = [];
        $bgColors = [];

        foreach ($opciones as $value => $opcion) {
            $count = $counts[$value] ?? 0;
            if ($count > 0) {
                $labels[] = $opcion['label'];
                $data[] = $count;
                $bgColors[] = $opcion['color'];
            }
        }

        $sinResultado = $counts->except(array_keys($opciones))->sum();
        if ($sinResultado > 0) {
            $labels[] = 'Sin Resultado';
            $data[] = $sinResultado;
            $bgColors[] = 'rgba(107,114,128,0.8)';
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
