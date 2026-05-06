<?php

use App\Livewire\Admin\ManageUsers;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::middleware('role:Administrador')->prefix('admin')->name('admin.')->group(function () {
        Route::get('usuarios', ManageUsers::class)->name('users');
    });
});

require __DIR__.'/settings.php';
