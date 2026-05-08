<?php

use App\Http\Controllers\ReportePdfController;
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
        Route::get('ordenes-trabajo/{orden}/reporte-pdf', [ReportePdfController::class, 'download'])->name('ordenes-trabajo.reporte-pdf');
        Route::get('ordenes-trabajo/{orden}/reportes', ReportesVigilanciaIndex::class)->name('ordenes-trabajo.reportes');
    });
