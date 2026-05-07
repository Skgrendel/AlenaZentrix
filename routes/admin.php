<?php

use App\Livewire\Admin\Configuracion;
use App\Livewire\Admin\EditRolePermisos;
use App\Livewire\Admin\ManageRoles;
use App\Livewire\Admin\ManageUsers;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:usuarios.ver'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('usuarios', ManageUsers::class)->name('users');
    });

Route::middleware(['auth', 'verified', 'permission:roles.ver'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('roles', ManageRoles::class)->name('roles');
    });

Route::middleware(['auth', 'verified', 'permission:roles.editar'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('roles/{role}/editar', EditRolePermisos::class)->name('roles.editar');
    });

Route::middleware(['auth', 'verified', 'permission:admin.configuracion'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('configuracion', Configuracion::class)->name('configuracion');
    });
