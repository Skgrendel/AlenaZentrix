<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\FiltraPorDashboard;
use App\Models\auditoria;
use App\Models\TipoAnomalia;
use Filament\Widgets\ChartWidget;

/**
 * Gráfico de barras con las anomalías más detectadas en las auditorías de Fraudes.
 */
class AnomaliasDetectadasChart extends ChartWidget
{
    use FiltraPorDashboard;

    protected ?string $heading = 'Anomalías Detectadas';

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '280px';

    protected function getData(): array
    {
        $counts = $this->aplicarFiltrosAuditoria(auditoria::query())
            ->whereNotNull('tipo_anomalia_id')
            ->selectRaw('tipo_anomalia_id, count(*) as total')
            ->groupBy('tipo_anomalia_id')
            ->orderByDesc('total')
            ->limit(10)
            ->pluck('total', 'tipo_anomalia_id');

        $nombres = TipoAnomalia::whereIn('id', $counts->keys())->pluck('nombre', 'id');

        $labels = [];
        $data = [];

        foreach ($counts as $tipoAnomaliaId => $total) {
            $labels[] = $nombres[$tipoAnomaliaId] ?? '—';
            $data[] = $total;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Detecciones',
                    'data' => $data,
                    'backgroundColor' => 'rgba(239,68,68,0.7)',
                    'borderColor' => 'rgb(239,68,68)',
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
            'indexAxis' => 'y',
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'x' => [
                    'beginAtZero' => true,
                    'ticks' => ['stepSize' => 1, 'precision' => 0],
                ],
            ],
        ];
    }
}
