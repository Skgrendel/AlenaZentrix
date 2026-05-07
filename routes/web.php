<?php

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

            if (! str_starts_with($path, 'ordenes/') && ! str_starts_with($path, 'reportes/') && ! str_starts_with($path, 'auditorias/')) {
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
});

require __DIR__.'/operaciones.php';
require __DIR__.'/tecnico.php';
require __DIR__.'/auditor.php';
require __DIR__.'/informes.php';
require __DIR__.'/admin.php';
require __DIR__.'/settings.php';
