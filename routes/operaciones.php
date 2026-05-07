<?php

use App\Livewire\Coordinador\OrdenJobShow;
use App\Livewire\Coordinador\OrdenesTrabajoIndex;
use App\Livewire\Coordinador\ReporteRevisar;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:ordenes.ver'])
    ->prefix('operaciones')
    ->name('operaciones.')
    ->group(function () {
        Route::get('ordenes-trabajo', OrdenesTrabajoIndex::class)->name('ordenes-trabajo');
        Route::get('ordenes-trabajo/{orden}', OrdenJobShow::class)->name('ordenes-trabajo.show');
        Route::get('ordenes-trabajo/{orden}/revisar', ReporteRevisar::class)->name('ordenes-trabajo.revisar');
    });
