<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToCheckExistence;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect('/login');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::middleware(['permission:evidencias.ver', 'signed'])
        ->get('operaciones/foto', function (Request $request) {
            $path = $request->query('path', '');

            $segments = explode('/', $path);
            if (in_array('.', $segments, true) || in_array('..', $segments, true)) {
                abort(403);
            }

            $companyPrefix = $request->user()->company_id.'/';
            $prefijosValidos = ['ordenes/', 'reportes/', 'auditorias/'];

            $esRutaValida = collect($prefijosValidos)->contains(
                fn (string $prefijo) => str_starts_with($path, $prefijo) || str_starts_with($path, $companyPrefix.$prefijo)
            );

            if (! $esRutaValida) {
                abort(403);
            }

            $disk = Storage::disk('s3');

            try {
                if (! $disk->exists($path)) {
                    abort(404);
                }
            } catch (UnableToCheckExistence) {
                abort(404);
            }

            $basename = basename($path);

            // Intentar obtener el MIME desde el disco (S3). Si falla, usar extensión.
            try {
                $mime = $disk->mimeType($path);
            } catch (Throwable $e) {
                $mime = null;
            }

            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $imageExtensions = ['jpg', 'jpeg', 'jfif', 'png', 'gif', 'webp', 'bmp', 'svg', 'tiff', 'tif', 'heic', 'heif', 'avif'];

            $isImage = false;
            if ($mime) {
                $isImage = str_starts_with($mime, 'image/');
            } else {
                $isImage = in_array($extension, $imageExtensions, true);
            }

            $disposition = $isImage ? 'inline' : 'attachment';

            return response()->stream(
                fn () => fpassthru($disk->readStream($path)),
                200,
                [
                    'Content-Type' => $mime ?? ($isImage ? 'image/jpeg' : 'application/octet-stream'),
                    'Cache-Control' => 'max-age=3600, private',
                    'Content-Disposition' => $disposition.'; filename="'.$basename.'"',
                ],
            );
        })->name('operaciones.foto');
});

require __DIR__.'/operaciones.php';
require __DIR__.'/tecnico.php';
require __DIR__.'/auditor.php';
require __DIR__.'/informes.php';
require __DIR__.'/admin.php';
require __DIR__.'/settings.php';
require __DIR__.'/mapa.php';
require __DIR__.'/superadmin.php';
require __DIR__.'/revision-medidores.php';
