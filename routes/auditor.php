<?php

use App\Livewire\Auditor\AuditoriaRevisar;
use App\Livewire\Auditor\AuditoriasIndex;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:auditorias.ver'])
    ->prefix('auditor')
    ->name('auditor.')
    ->group(function () {
        Route::get('auditorias', AuditoriasIndex::class)->name('auditorias');
        Route::get('auditorias/{auditoria}/revisar', AuditoriaRevisar::class)->name('auditorias.revisar');
    });
