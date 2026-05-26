<?php

use App\Livewire\SuperAdmin\Companies\CompaniesIndex;
use App\Livewire\SuperAdmin\Dashboard;
use App\Livewire\SuperAdmin\Roles\EditRolePermisos;
use App\Livewire\SuperAdmin\Roles\RolesIndex;
use App\Livewire\SuperAdmin\Users\UsersIndex;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'superadmin'])
    ->prefix('superadmin')
    ->name('superadmin.')
    ->group(function () {
        Route::get('/', Dashboard::class)->name('dashboard');
        Route::get('companies', CompaniesIndex::class)->name('companies');
        Route::get('users', UsersIndex::class)->name('users');
        Route::get('roles', RolesIndex::class)->name('roles');
        Route::get('roles/{role}/editar', EditRolePermisos::class)->name('roles.editar');
    });
