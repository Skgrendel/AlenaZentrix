<?php

namespace App\Filament\Widgets;

use App\Enums\TipoServicio;
use App\Models\OrdenJob;
use Filament\Widgets\ChartWidget;

class OrdenesPorServicioChart extends ChartWidget
{
    protected ?string $heading = 'Por Tipo de Servicio';

    protected ?string $pollingInterval = null;

    protected function getData(): array
    {
        $counts = OrdenJob::selectRaw('tipo_servicio, count(*) as total')
            ->groupBy('tipo_servicio')
            ->pluck('total', 'tipo_servicio');

        $colorMap = [
            'fraudes' => 'rgba(239,68,68,0.8)',
            'investigacion' => 'rgba(245,158,11,0.8)',
            'capturas' => 'rgba(59,130,246,0.8)',
            'bandas' => 'rgba(139,92,246,0.8)',
            'vigilancia' => 'rgba(16,185,129,0.8)',
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
