<?php

use App\Livewire\Coordinador\SurveyAuditoria\SurveyRegistrosIndex;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:ordenes.ver'])
    ->prefix('operaciones/survey-auditoria')
    ->name('operaciones.survey-auditoria.')
    ->group(function () {
        Route::get('/', SurveyRegistrosIndex::class)->name('index');
    });
