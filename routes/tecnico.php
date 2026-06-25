<?php

use App\Livewire\Auditor\AuditoriaRevisar;
use App\Livewire\Tecnico\MisOrdenesIndex;
use App\Livewire\Tecnico\OrdenShow;
use App\Livewire\Tecnico\ReporteCreate;
use App\Livewire\Tecnico\ReporteEdit;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:mis-ordenes.ver'])
    ->prefix('tecnico')
    ->name('tecnico.')
    ->group(function () {
        Route::get('mis-ordenes', MisOrdenesIndex::class)->name('mis-ordenes');
        Route::get('mis-ordenes/{auditoria}/auditar', AuditoriaRevisar::class)->name('mis-ordenes.auditar');
        Route::get('ordenes/{orden}', OrdenShow::class)->name('ordenes.show');
        Route::get('ordenes/{orden}/reportar', ReporteCreate::class)->name('ordenes.reportar');
        Route::get('ordenes/{orden}/reporte/editar', ReporteEdit::class)->name('ordenes.reporte.editar');
    });
