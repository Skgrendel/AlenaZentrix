<?php

use App\Livewire\Auditor\AuditoriaRevisar;
use App\Livewire\Auditor\AuditoriasIndex;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:Administrador|Auditor'])
    ->prefix('auditor')
    ->name('auditor.')
    ->group(function () {
        Route::get('auditorias', AuditoriasIndex::class)->name('auditorias');
    });

Route::middleware(['auth', 'verified', 'role:Administrador|Auditor|Coordinador'])
    ->get('auditor/auditorias/{auditoria}/revisar', AuditoriaRevisar::class)
    ->name('auditor.auditorias.revisar');
