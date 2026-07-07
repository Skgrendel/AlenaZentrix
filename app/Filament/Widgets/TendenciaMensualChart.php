<?php

namespace App\Filament\Widgets;

use App\Enums\EstadoOrden;
use App\Models\OrdenJob;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Livewire\Attributes\On;

class TendenciaMensualChart extends ChartWidget
{
    protected ?string $heading = 'Tendencia Mensual de Órdenes';

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
        $inicio = $this->desde ? Carbon::parse($this->desde)->startOfMonth() : now()->subMonths(5)->startOfMonth();
        $fin = $this->hasta ? Carbon::parse($this->hasta)->startOfMonth() : now()->startOfMonth();

        if ($fin->lt($inicio)) {
            $fin = $inicio;
        }

        if ($inicio->diffInMonths($fin) > 24) {
            $inicio = $fin->subMonths(24);
        }

        $labels = [];
        $ingresadas = [];
        $completadas = [];
        $rechazadas = [];

        for ($date = $inicio; $date->lte($fin); $date = $date->addMonth()) {
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
