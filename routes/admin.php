<?php

use App\Livewire\Admin\Configuracion;
use App\Livewire\Admin\ManageUsers;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:Administrador'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('usuarios', ManageUsers::class)->name('users');
        Route::get('configuracion', Configuracion::class)->name('configuracion');
    });
