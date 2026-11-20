<?php

use App\Http\Controllers\AvisoSuscripcionController;
use App\Http\Controllers\OrdenReferenciaController;
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
        Route::post('avisos', [AvisoSuscripcionController::class, 'guardar'])->name('avisos.guardar');
        Route::delete('avisos', [AvisoSuscripcionController::class, 'quitar'])->name('avisos.quitar');
        Route::get('mis-ordenes/{auditoria}/auditar', AuditoriaRevisar::class)->name('mis-ordenes.auditar');
        Route::get('ordenes/{orden}', OrdenShow::class)->name('ordenes.show');
        Route::get('ordenes/{orden}/referencia/fachada', [OrdenReferenciaController::class, 'fachada'])->name('ordenes.referencia.fachada');
        Route::get('ordenes/{orden}/referencia/mapa', [OrdenReferenciaController::class, 'mapa'])->name('ordenes.referencia.mapa');
        Route::get('ordenes/{orden}/reportar', ReporteCreate::class)->name('ordenes.reportar');
        Route::get('ordenes/{orden}/reporte/editar', ReporteEdit::class)->name('ordenes.reporte.editar');
    });
