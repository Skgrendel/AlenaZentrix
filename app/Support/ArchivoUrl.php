<?php

namespace App\Support;

use Illuminate\Support\Facades\URL;

/**
 * Genera URLs firmadas hacia la ruta `operaciones.foto`, usada por el panel web
 * para mostrar archivos privados de S3 sin exponer el bucket públicamente.
 */
final class ArchivoUrl
{
    public static function temporal(string $path, int $minutos = 30): string
    {
        return URL::temporarySignedRoute(
            'operaciones.foto',
            now()->addMinutes($minutos),
            ['path' => $path],
        );
    }
}
