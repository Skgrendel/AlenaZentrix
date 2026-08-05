<?php

namespace App\Filament\Widgets;

use App\Enums\EstadoOrden;
use App\Filament\Widgets\Concerns\FiltraPorDashboard;
use App\Models\OrdenJob;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

/**
 * Gráfico de barras que muestra la distribución de órdenes por estado.
 * El coordinador ve todas las órdenes; el técnico solo las propias.
 */
class OrdenesPorEstadoChart extends ChartWidget
{
    use FiltraPorDashboard;

    protected ?string $heading = 'Órdenes por Estado';

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '280px';

    protected function getData(): array
    {
        $user = Auth::user();

        $base = $user->can('ordenes.ver')
            ? OrdenJob::query()
            : OrdenJob::where('personals_id', $user->personals_id);

        $counts = $this->aplicarFiltrosOrdenJob($base)
            ->selectRaw('estado, count(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        $colorMap = [
            'pendiente' => ['bg' => 'rgba(107,114,128,0.7)', 'border' => 'rgb(107,114,128)'],
            'asignada' => ['bg' => 'rgba(59,130,246,0.7)',  'border' => 'rgb(59,130,246)'],
            'en_progreso' => ['bg' => 'rgba(245,158,11,0.7)',  'border' => 'rgb(245,158,11)'],
            'en_revision' => ['bg' => 'rgba(139,92,246,0.7)',  'border' => 'rgb(139,92,246)'],
            'completada' => ['bg' => 'rgba(16,185,129,0.7)',  'border' => 'rgb(16,185,129)'],
            'rechazada' => ['bg' => 'rgba(239,68,68,0.7)',   'border' => 'rgb(239,68,68)'],
            'cancelada' => ['bg' => 'rgba(75,85,99,0.7)',    'border' => 'rgb(75,85,99)'],
        ];

        $labels = [];
        $data = [];
        $bgColors = [];
        $borderColors = [];

        foreach (EstadoOrden::cases() as $estado) {
            $labels[] = $estado->label();
            $data[] = $counts[$estado->value] ?? 0;
            $bgColors[] = $colorMap[$estado->value]['bg'];
            $borderColors[] = $colorMap[$estado->value]['border'];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Órdenes',
                    'data' => $data,
                    'backgroundColor' => $bgColors,
                    'borderColor' => $borderColors,
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
