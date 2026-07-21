<?php

namespace App\Filament\Widgets;

use App\Enums\EstadoOrden;
use App\Models\OrdenJob;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;

/**
 * Gráfico de barras que muestra la distribución de órdenes por estado.
 * El coordinador ve todas las órdenes; el técnico solo las propias.
 */
class OrdenesPorEstadoChart extends ChartWidget
{
    protected ?string $heading = 'Órdenes por Estado';

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

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
        $user = Auth::user();

        $base = $user->can('ordenes.ver')
            ? OrdenJob::query()
            : OrdenJob::where('personals_id', $user->personals_id);

        $counts = $base
            ->when($this->desde, fn ($q, $desde) => $q->whereDate('created_at', '>=', $desde))
            ->when($this->hasta, fn ($q, $hasta) => $q->whereDate('created_at', '<=', $hasta))
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
