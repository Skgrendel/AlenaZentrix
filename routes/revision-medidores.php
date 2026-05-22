<?php

use App\Http\Controllers\ReportePdfController;
use App\Http\Controllers\RevisionMedidoresPlantillaController;
use App\Livewire\Coordinador\RevisionMedidores\ImportarRevisionMedidores;
use App\Livewire\Coordinador\RevisionMedidores\RevisionMedidoresIndex;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:ordenes.ver'])
    ->prefix('operaciones/revision-medidores')
    ->name('operaciones.revision-medidores.')
    ->group(function () {
        Route::get('/', RevisionMedidoresIndex::class)->name('index');

        Route::middleware('permission:ordenes.crear')->group(function () {
            Route::get('importar', ImportarRevisionMedidores::class)->name('importar');
            Route::get('plantilla', [RevisionMedidoresPlantillaController::class, 'download'])->name('plantilla');
        });

        Route::get('{orden}/reporte', [ReportePdfController::class, 'DownLoadInformeRevisionMedidores'])
            ->middleware('permission:informes')
            ->name('reporte');

        Route::get('descargar-zip', [ReportePdfController::class, 'descargarZipRevisionMedidores'])
            ->middleware('signed')
            ->name('descargar-zip');
    });
