<?php

namespace App\Filament\Widgets;

use App\Enums\EstadoAuditoria;
use App\Enums\EstadoOrden;
use App\Enums\TipoServicio;
use App\Filament\Widgets\Concerns\FiltraPorDashboard;
use App\Models\auditoria;
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
    use FiltraPorDashboard;

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
        $enRango = fn () => $this->aplicarFiltrosOrdenJob(OrdenJob::query());

        $filtroActivo = $this->desde || $this->hasta || $this->estado || $this->prioridad
            || $this->tipoServicio || $this->personalsId || $this->resultado
            || $this->estadoAuditoria || $this->tipoAnomaliaId || $this->procesoId;

        $total = $enRango()->count();
        $pendientes = $enRango()->where('estado', EstadoOrden::Pendiente)->count();
        $enProgreso = $enRango()->where('estado', EstadoOrden::EnProgreso)->count();
        $enRevision = $enRango()->where('estado', EstadoOrden::EnRevision)->count();

        $completadasTotal = $enRango()->where('estado', EstadoOrden::Completada)->count();

        $entregadasPendienteRevision = $this->aplicarFiltrosAuditoria(
            auditoria::where('estado', EstadoAuditoria::PendienteAuditoria)
                ->whereHas('reporte.ordenJob', fn ($q) => $q->where('tipo_servicio', TipoServicio::Fraudes))
        )->count();

        if ($filtroActivo) {
            $completadas = $completadasTotal;
            $tendenciaDesc = 'En el rango seleccionado';
            $chart = [];
            $chartColor = 'success';
            $color = 'success';
        } else {
            $completadasMes = $enRango()->where('estado', EstadoOrden::Completada)
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

            $completadas = $completadasMes;
            $tendenciaDesc = $tendencia !== null
                ? ($tendencia >= 0 ? "+{$tendencia}% vs mes anterior" : "{$tendencia}% vs mes anterior")
                : 'Este mes';
            $chart = $sparkline;
            $chartColor = 'success';
            $color = $tendencia !== null && $tendencia < 0 ? 'danger' : 'success';
        }

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
            Stat::make('Completadas', $completadas)
                ->description($tendenciaDesc)
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->when($chart !== [], fn (Stat $stat) => $stat->chart($chart)->chartColor($chartColor))
                ->color($color),
            Stat::make('Entregadas pendiente de Revisión', $entregadasPendienteRevision)
                ->description('Pendientes por auditar')
                ->descriptionIcon('heroicon-m-clipboard-document-check')
                ->color('warning'),
        ];
    }

    /**
     * @param  string|null  $personalsId  Puede ser null si el usuario no tiene personal vinculado; devuelve ceros.
     * @return array<int, Stat>
     */
    private function statsTecnico(?string $personalsId): array
    {
        $base = $this->aplicarFiltrosOrdenJob(OrdenJob::where('personals_id', $personalsId));

        $filtroActivo = $this->desde || $this->hasta;

        $asignadas = (clone $base)->where('estado', EstadoOrden::Asignada)->count();
        $enProgreso = (clone $base)->where('estado', EstadoOrden::EnProgreso)->count();
        $enRevision = (clone $base)->where('estado', EstadoOrden::EnRevision)->count();

        $completadas = $filtroActivo
            ? (clone $base)->where('estado', EstadoOrden::Completada)->count()
            : (clone $base)
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
            Stat::make('Completadas', $completadas)
                ->description($filtroActivo ? 'En el rango seleccionado' : 'Este mes')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
        ];
    }
}
