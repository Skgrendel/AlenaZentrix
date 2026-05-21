<?php

use App\Http\Controllers\ReportePdfController;
use App\Livewire\Coordinador\ConsultaGis\ConsultaGisIndex;
use App\Livewire\Coordinador\OrdenCreate;
use App\Livewire\Coordinador\OrdenesTrabajoIndex;
use App\Livewire\Coordinador\OrdenJobShow;
use App\Livewire\Coordinador\ReporteRevisar;
use App\Livewire\Coordinador\ReportesVigilanciaIndex;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:ordenes.ver'])
    ->prefix('operaciones')
    ->name('operaciones.')
    ->group(function () {
        Route::get('ordenes-trabajo', OrdenesTrabajoIndex::class)->name('ordenes-trabajo');

        // 'crear' debe ir antes que {orden} para evitar colisión de rutas
        Route::middleware('permission:ordenes.crear')
            ->get('ordenes-trabajo/crear', OrdenCreate::class)
            ->name('ordenes-trabajo.crear');

        Route::get('ordenes-trabajo/{orden}', OrdenJobShow::class)->name('ordenes-trabajo.show');
        Route::get('ordenes-trabajo/{orden}/revisar', ReporteRevisar::class)->name('ordenes-trabajo.revisar');
        Route::get('ordenes-trabajo/{orden}/reporte-fraude', [ReportePdfController::class, 'DownLoadInformeFraude'])->name('ordenes-trabajo.reporte-fraude');
        Route::get('ordenes-trabajo/{orden}/reporte-investigacion', [ReportePdfController::class, 'DownLoadInformeInvestigacion'])->name('ordenes-trabajo.reporte-investigacion');
        Route::get('ordenes-trabajo/{orden}/reporte-gestion-legal', [ReportePdfController::class, 'DownLoadInformeGestionLegal'])->name('ordenes-trabajo.reporte-gestion-legal');
        Route::get('ordenes-trabajo/{orden}/reporte-auditoria-campo', [ReportePdfController::class, 'DownLoadInformeAuditoriaCampo'])->name('ordenes-trabajo.reporte-auditoria-campo');
        Route::get('ordenes-trabajo/{orden}/reporte-gestion-riesgo', [ReportePdfController::class, 'DownLoadInformeGestionRiesgo'])->name('ordenes-trabajo.reporte-gestion-riesgo');
        Route::get('ordenes-trabajo/{orden}/reportes', ReportesVigilanciaIndex::class)->name('ordenes-trabajo.reportes');

        Route::get('consulta-gis', ConsultaGisIndex::class)->name('consulta-gis');
    });
