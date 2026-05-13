<?php

use App\Livewire\Mapa\MapaIndex;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:mapa.ver'])
    ->prefix('mapa')
    ->name('mapa.')
    ->group(function () {
        Route::get('/', MapaIndex::class)->name('index');
    });
