<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToCheckExistence;

class ArchivoController extends Controller
{
    /**
     * Genera una URL temporal pre-firmada para acceder a un archivo de S3.
     * La URL expira en 15 minutos.
     */
    public function show(string $path): JsonResponse
    {
        $path = ltrim($path, '/');

        try {
            if (! Storage::disk('s3')->exists($path)) {
                return response()->json(['message' => 'Archivo no encontrado.'], 404);
            }
        } catch (UnableToCheckExistence) {
            return response()->json(['message' => 'Archivo no encontrado.'], 404);
        }

        $url = Storage::disk('s3')->temporaryUrl($path, now()->addMinutes(15));

        return response()->json(['url' => $url]);
    }
}
