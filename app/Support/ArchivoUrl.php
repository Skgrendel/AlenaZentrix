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

    /**
     * Detecta referencias temporales de subida de Livewire (formato "livewire-file:...")
     * que quedaron guardadas en vez de la ruta final en S3 —el archivo nunca llegó a subirse—,
     * para poder excluirlas antes de intentar mostrarlas como evidencia.
     */
    public static function esReferenciaTemporal(string $path): bool
    {
        return str_starts_with($path, 'livewire-file:');
    }
}
