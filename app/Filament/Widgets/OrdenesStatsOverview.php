<?php

namespace App\Filament\Widgets;

use App\Enums\EstadoOrden;
use App\Models\OrdenJob;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

/**
 * Widget de tarjetas de resumen en el dashboard. Muestra métricas distintas según
 * si el usuario tiene permisos de coordinador (`ordenes.ver`) o es técnico.
 */
class OrdenesStatsOverview extends BaseWidget
{
    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $user = Auth::user();

        if ($user->can('ordenes.ver')) {
            return $this->statsCoordinador();
        }

        return $this->statsTecnico($user->personals_id);
    }

    /** @return array<int, Stat> Estadísticas globales para el coordinador/admin. */
    private function statsCoordinador(): array
    {
        $total = OrdenJob::count();
        $pendientes = OrdenJob::where('estado', EstadoOrden::Pendiente)->count();
        $enProgreso = OrdenJob::where('estado', EstadoOrden::EnProgreso)->count();
        $enRevision = OrdenJob::where('estado', EstadoOrden::EnRevision)->count();

        $completadasMes = OrdenJob::where('estado', EstadoOrden::Completada)
            ->whereMonth('fecha_completado', now()->month)
            ->whereYear('fecha_completado', now()->year)
            ->count();

        $completadasMesAnterior = OrdenJob::where('estado', EstadoOrden::Completada)
            ->whereMonth('fecha_completado', now()->subMonth()->month)
            ->whereYear('fecha_completado', now()->subMonth()->year)
            ->count();

        $tendencia = $completadasMesAnterior > 0
            ? round(($completadasMes - $completadasMesAnterior) / $completadasMesAnterior * 100)
            : null;

        $sparkline = [];
        for ($i = 5; $i >= 0; $i--) {
            $d = now()->subMonths($i);
            $sparkline[] = OrdenJob::where('estado', EstadoOrden::Completada)
                ->whereYear('fecha_completado', $d->year)
                ->whereMonth('fecha_completado', $d->month)
                ->count();
        }

        $completadasTotal = OrdenJob::where('estado', EstadoOrden::Completada)->count();
        $rechazadasTotal = OrdenJob::where('estado', EstadoOrden::Rechazada)->count();
        $cerradas = $completadasTotal + $rechazadasTotal;
        $eficiencia = $cerradas > 0 ? round($completadasTotal / $cerradas * 100) : null;

        $tendenciaDesc = $tendencia !== null
            ? ($tendencia >= 0 ? "+{$tendencia}% vs mes anterior" : "{$tendencia}% vs mes anterior")
            : 'Este mes';

        return [
            Stat::make('Total Órdenes', $total)
                ->description('Registradas en el sistema')
                ->descriptionIcon('heroicon-m-clipboard-document-list')
                ->color('primary'),
            Stat::make('Pendientes', $pendientes)
                ->description('Sin asignar')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),
            Stat::make('En Progreso', $enProgreso)
                ->description('En atención')
                ->descriptionIcon('heroicon-m-wrench-screwdriver')
                ->color('info'),
            Stat::make('En Revisión', $enRevision)
                ->description('Esperando aprobación')
                ->descriptionIcon('heroicon-m-document-magnifying-glass')
                ->color('warning'),
            Stat::make('Completadas', $completadasMes)
                ->description($tendenciaDesc)
                ->descriptionIcon($tendencia !== null && $tendencia >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->chart($sparkline)
                ->chartColor('success')
                ->color($tendencia !== null && $tendencia < 0 ? 'danger' : 'success'),
            Stat::make('Eficiencia', $eficiencia !== null ? "{$eficiencia}%" : 'N/D')
                ->description('Completadas vs rechazadas')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color(match (true) {
                    $eficiencia === null => 'gray',
                    $eficiencia >= 80 => 'success',
                    $eficiencia >= 60 => 'warning',
                    default => 'danger',
                }),
        ];
    }

    /**
     * @param  string|null  $personalsId  Puede ser null si el usuario no tiene personal vinculado; devuelve ceros.
     * @return array<int, Stat>
     */
    private function statsTecnico(?string $personalsId): array
    {
        $base = OrdenJob::where('personals_id', $personalsId);

        $asignadas = (clone $base)->where('estado', EstadoOrden::Asignada)->count();
        $enProgreso = (clone $base)->where('estado', EstadoOrden::EnProgreso)->count();
        $enRevision = (clone $base)->where('estado', EstadoOrden::EnRevision)->count();
        $completadasMes = (clone $base)
            ->where('estado', EstadoOrden::Completada)
            ->whereMonth('fecha_completado', now()->month)
            ->whereYear('fecha_completado', now()->year)
            ->count();

        return [
            Stat::make('Asignadas', $asignadas)
                ->description('Pendientes de iniciar')
                ->descriptionIcon('heroicon-m-clipboard-document-check')
                ->color('primary'),
            Stat::make('En Progreso', $enProgreso)
                ->description('Atendiendo ahora')
                ->descriptionIcon('heroicon-m-wrench-screwdriver')
                ->color('info'),
            Stat::make('En Revisión', $enRevision)
                ->description('Esperando aprobación')
                ->descriptionIcon('heroicon-m-document-magnifying-glass')
                ->color('warning'),
            Stat::make('Completadas', $completadasMes)
                ->description('Este mes')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
        ];
    }
}
