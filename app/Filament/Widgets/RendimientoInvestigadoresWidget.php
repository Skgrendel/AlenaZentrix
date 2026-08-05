<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\FiltraPorDashboard;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RendimientoInvestigadoresWidget extends Widget
{
    use FiltraPorDashboard;

    protected string $view = 'filament.widgets.rendimiento-investigadores-widget';

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    public function getInvestigadores(): Collection
    {
        $companyId = auth()->user()?->company_id;

        return DB::table('personals as p')
            ->leftJoin('orden_jobs as oj', function ($join) use ($companyId) {
                $join->on('oj.personals_id', '=', 'p.id')
                    ->where('oj.company_id', $companyId)
                    ->when($this->desde, fn ($join, $desde) => $join->whereDate('oj.created_at', '>=', $desde))
                    ->when($this->hasta, fn ($join, $hasta) => $join->whereDate('oj.created_at', '<=', $hasta))
                    ->when($this->estado, fn ($join, $estado) => $join->where('oj.estado', $estado))
                    ->when($this->prioridad, fn ($join, $prioridad) => $join->where('oj.prioridad', $prioridad))
                    ->when($this->tipoServicio, fn ($join, $tipoServicio) => $join->where('oj.tipo_servicio', $tipoServicio))
                    ->when($this->resultado, fn ($join, $resultado) => $join->where('oj.resultado', $resultado))
                    ->when($this->procesoId, fn ($join, $procesoId) => $join->where('oj.proceso_id', $procesoId));
            })
            ->where('p.company_id', $companyId)
            ->when($this->personalsId, fn ($query, $personalsId) => $query->where('p.id', $personalsId))
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
