<?php

use App\Livewire\Admin\Configuracion;
use App\Livewire\Admin\ManageUsers;
use App\Livewire\Coordinador\OrdenesTrabajoIndex;
use App\Livewire\Coordinador\OrdenJobShow;
use App\Livewire\Coordinador\ReporteRevisar;
use App\Livewire\Tecnico\MisOrdenesIndex;
use App\Livewire\Tecnico\ReporteCreate;
use App\Livewire\Tecnico\ReporteEdit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::middleware('role:Administrador|Coordinador|Lector')
        ->get('operaciones/foto', function (Request $request) {
            $path = $request->query('path', '');

            if (! str_starts_with($path, 'ordenes/') && ! str_starts_with($path, 'reportes/')) {
                abort(403);
            }

            $disk = Storage::disk('s3');

            if (! $disk->exists($path)) {
                abort(404);
            }

            return response()->stream(
                fn () => fpassthru($disk->readStream($path)),
                200,
                ['Content-Type' => $disk->mimeType($path), 'Cache-Control' => 'max-age=3600, private'],
            );
        })->name('operaciones.foto');

    Route::middleware('role:Administrador|Coordinador')->prefix('operaciones')->name('operaciones.')->group(function () {
        Route::get('ordenes-trabajo', OrdenesTrabajoIndex::class)->name('ordenes-trabajo');
        Route::get('ordenes-trabajo/{orden}', OrdenJobShow::class)->name('ordenes-trabajo.show');
        Route::get('ordenes-trabajo/{orden}/revisar', ReporteRevisar::class)->name('ordenes-trabajo.revisar');
    });

    Route::middleware('role:Administrador|Lector')->prefix('tecnico')->name('tecnico.')->group(function () {
        Route::get('mis-ordenes', MisOrdenesIndex::class)->name('mis-ordenes');
        Route::get('ordenes/{orden}/reportar', ReporteCreate::class)->name('ordenes.reportar');
        Route::get('ordenes/{orden}/reporte/editar', ReporteEdit::class)->name('ordenes.reporte.editar');
    });

    Route::middleware('role:Administrador')->prefix('admin')->name('admin.')->group(function () {
        Route::get('usuarios', ManageUsers::class)->name('users');
        Route::get('configuracion', Configuracion::class)->name('configuracion');
    });
});

require __DIR__.'/settings.php';
