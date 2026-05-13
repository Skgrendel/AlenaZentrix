<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'superadmin'])
    ->prefix('superadmin')
    ->name('superadmin.')
    ->group(function () {
        Route::view('/', 'livewire.super-admin.dashboard')->name('dashboard');
    });
