<?php

use App\Http\Controllers\InformeController;
use App\Livewire\Informes\InformesIndex;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])
    ->prefix('informes')
    ->name('informes.')
    ->group(function () {
        Route::get('/', InformesIndex::class)->name('index');
        Route::get('descargar', [InformeController::class, 'download'])->name('descargar');
    });
