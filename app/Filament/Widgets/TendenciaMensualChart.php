<?php

namespace App\Filament\Widgets;

use App\Enums\EstadoOrden;
use App\Models\OrdenJob;
use Filament\Widgets\ChartWidget;

class TendenciaMensualChart extends ChartWidget
{
    protected ?string $heading = 'Tendencia Mensual de Órdenes';

    protected ?string $pollingInterval = null;

    protected function getData(): array
    {
        $labels = [];
        $ingresadas = [];
        $completadas = [];
        $rechazadas = [];

        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $labels[] = ucfirst($date->isoFormat('MMM YY'));

            $ingresadas[] = OrdenJob::whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->count();

            $completadas[] = OrdenJob::where('estado', EstadoOrden::Completada)
                ->whereYear('fecha_completado', $date->year)
                ->whereMonth('fecha_completado', $date->month)
                ->count();

            $rechazadas[] = OrdenJob::where('estado', EstadoOrden::Rechazada)
                ->whereYear('updated_at', $date->year)
                ->whereMonth('updated_at', $date->month)
                ->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Ingresadas',
                    'data' => $ingresadas,
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.08)',
                    'fill' => true,
                    'tension' => 0.4,
                    'pointRadius' => 4,
                    'pointHoverRadius' => 6,
                ],
                [
                    'label' => 'Completadas',
                    'data' => $completadas,
                    'borderColor' => 'rgb(16, 185, 129)',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.08)',
                    'fill' => true,
                    'tension' => 0.4,
                    'pointRadius' => 4,
                    'pointHoverRadius' => 6,
                ],
                [
                    'label' => 'Rechazadas',
                    'data' => $rechazadas,
                    'borderColor' => 'rgb(239, 68, 68)',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.08)',
                    'fill' => true,
                    'tension' => 0.4,
                    'pointRadius' => 4,
                    'pointHoverRadius' => 6,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['position' => 'top'],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => ['stepSize' => 1, 'precision' => 0],
                    'grid' => ['color' => 'rgba(0,0,0,0.04)'],
                ],
                'x' => [
                    'grid' => ['display' => false],
                ],
            ],
        ];
    }
}
