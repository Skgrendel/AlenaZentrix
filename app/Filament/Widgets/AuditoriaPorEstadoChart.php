<?php

namespace App\Filament\Widgets;

use App\Enums\EstadoAuditoria;
use App\Filament\Widgets\Concerns\FiltraPorDashboard;
use App\Models\auditoria;
use Filament\Widgets\ChartWidget;

/**
 * Gráfico de dona que muestra la distribución de auditorías de Fraudes por estado
 * (Pendiente por auditar, Confirmado, Negativo).
 */
class AuditoriaPorEstadoChart extends ChartWidget
{
    use FiltraPorDashboard;

    protected ?string $heading = 'Estado de Auditoría';

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '240px';

    protected function getData(): array
    {
        $counts = $this->aplicarFiltrosAuditoria(auditoria::query())
            ->selectRaw('estado, count(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        $colorMap = [
            'pendiente_auditoria' => 'rgba(245,158,11,0.8)',
            'confirmado' => 'rgba(16,185,129,0.8)',
            'negativo' => 'rgba(239,68,68,0.8)',
        ];

        $labels = [];
        $data = [];
        $bgColors = [];

        foreach (EstadoAuditoria::cases() as $estado) {
            $count = $counts[$estado->value] ?? 0;
            if ($count > 0) {
                $labels[] = $estado->label();
                $data[] = $count;
                $bgColors[] = $colorMap[$estado->value] ?? 'rgba(107,114,128,0.8)';
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
