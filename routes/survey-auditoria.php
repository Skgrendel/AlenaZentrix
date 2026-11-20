<?php

use App\Http\Controllers\SurveyAdjuntoController;
use App\Livewire\Coordinador\SurveyAuditoria\SurveyRegistroShow;
use App\Livewire\Coordinador\SurveyAuditoria\SurveyRegistrosIndex;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:ordenes.ver'])
    ->prefix('operaciones/survey-auditoria')
    ->name('operaciones.survey-auditoria.')
    ->group(function () {
        Route::get('/', SurveyRegistrosIndex::class)->name('index');
        Route::get('adjuntos/{adjunto}', [SurveyAdjuntoController::class, 'show'])->name('adjuntos.show');
        Route::get('{registro}', SurveyRegistroShow::class)->name('show');
    });
