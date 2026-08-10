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

        $segments = explode('/', $path);
        if (in_array('.', $segments, true) || in_array('..', $segments, true)) {
            return response()->json(['message' => 'Ruta inválida.'], 403);
        }

        $companyId = auth()->user()->company_id;
        if (! str_starts_with($path, $companyId.'/')) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        try {
            if (! Storage::disk('s3')->exists($path)) {
                return response()->json(['message' => 'Archivo no encontrado.'], 404);
            }
        } catch (UnableToCheckExistence) {
            return response()->json(['message' => 'Archivo no encontrado.'], 404);
        }

        $url = Storage::disk('s3_directo')->temporaryUrl($path, now()->addMinutes(15));

        return response()->json(['url' => $url]);
    }
}
