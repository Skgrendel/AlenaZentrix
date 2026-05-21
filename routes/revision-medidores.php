<?php

use App\Http\Controllers\RevisionMedidoresPlantillaController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:ordenes.ver'])
    ->prefix('operaciones/revision-medidores')
    ->name('operaciones.revision-medidores.')
    ->group(function () {
        Route::get('plantilla', [RevisionMedidoresPlantillaController::class, 'download'])
            ->middleware('permission:ordenes.crear')
            ->name('plantilla');
    });
