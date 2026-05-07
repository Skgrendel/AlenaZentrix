<?php

use App\Http\Controllers\InformeController;
use App\Livewire\Admin\Configuracion;
use App\Livewire\Admin\ManageUsers;
use App\Livewire\Auditor\AuditoriaRevisar;
use App\Livewire\Auditor\AuditoriasIndex;
use App\Livewire\Coordinador\OrdenesTrabajoIndex;
use App\Livewire\Coordinador\OrdenJobShow;
use App\Livewire\Coordinador\ReporteRevisar;
use App\Livewire\Informes\InformesIndex;
use App\Livewire\Tecnico\MisOrdenesIndex;
use App\Livewire\Tecnico\ReporteCreate;
use App\Livewire\Tecnico\ReporteEdit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect('/login');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::middleware('role:Administrador|Coordinador|Lector|Auditor')
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

    Route::middleware('role:Administrador|Auditor')->prefix('auditor')->name('auditor.')->group(function () {
        Route::get('auditorias', AuditoriasIndex::class)->name('auditorias');
        Route::get('auditorias/{auditoria}/revisar', AuditoriaRevisar::class)->name('auditorias.revisar');
    });

    Route::prefix('informes')->name('informes.')->group(function () {
        Route::get('/', InformesIndex::class)->name('index');
        Route::get('descargar', [InformeController::class, 'download'])->name('descargar');
    });

    Route::middleware('role:Administrador')->prefix('admin')->name('admin.')->group(function () {
        Route::get('usuarios', ManageUsers::class)->name('users');
        Route::get('configuracion', Configuracion::class)->name('configuracion');
    });
});

require __DIR__.'/settings.php';
