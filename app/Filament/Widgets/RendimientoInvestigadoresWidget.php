<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

class RendimientoInvestigadoresWidget extends Widget
{
    protected string $view = 'filament.widgets.rendimiento-investigadores-widget';

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

    public function getInvestigadores(): Collection
    {
        $companyId = auth()->user()?->company_id;

        return DB::table('personals as p')
            ->leftJoin('orden_jobs as oj', function ($join) use ($companyId) {
                $join->on('oj.personals_id', '=', 'p.id')
                    ->where('oj.company_id', $companyId)
                    ->when($this->desde, fn ($join, $desde) => $join->whereDate('oj.created_at', '>=', $desde))
                    ->when($this->hasta, fn ($join, $hasta) => $join->whereDate('oj.created_at', '<=', $hasta));
            })
            ->where('p.company_id', $companyId)
            ->select([
                'p.nombres',
                'p.apellidos',
                'p.cargo',
                DB::raw('COUNT(oj.id) as total'),
                DB::raw("SUM(CASE WHEN oj.estado IN ('asignada', 'en_progreso', 'en_revision') THEN 1 ELSE 0 END) as activas"),
                DB::raw("SUM(CASE WHEN oj.estado = 'completada' AND MONTH(oj.fecha_completado) = MONTH(NOW()) AND YEAR(oj.fecha_completado) = YEAR(NOW()) THEN 1 ELSE 0 END) as completadas_mes"),
                DB::raw("SUM(CASE WHEN oj.estado = 'completada' THEN 1 ELSE 0 END) as completadas_total"),
                DB::raw("SUM(CASE WHEN oj.estado = 'rechazada' THEN 1 ELSE 0 END) as rechazadas"),
                DB::raw('ROUND(AVG(CASE WHEN oj.fecha_completado IS NOT NULL AND oj.fecha_asignacion IS NOT NULL THEN DATEDIFF(oj.fecha_completado, oj.fecha_asignacion) END)) as promedio_dias'),
            ])
            ->groupBy('p.id', 'p.nombres', 'p.apellidos', 'p.cargo')
            ->orderByDesc('completadas_mes')
            ->orderByDesc('total')
            ->get()
            ->map(function (object $row): object {
                $cerradas = $row->completadas_total + $row->rechazadas;
                $row->efectividad = $cerradas > 0 ? round($row->completadas_total / $cerradas * 100) : null;
                $row->nombre_completo = "{$row->nombres} {$row->apellidos}";
                $row->iniciales = mb_strtoupper(mb_substr($row->nombres, 0, 1).mb_substr($row->apellidos, 0, 1));

                return $row;
            });
    }
}
