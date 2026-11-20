<?php

namespace App\Http\Controllers;

use App\Models\SurveyAdjunto;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Entrega las fotos de Survey123 copiadas a nuestro almacenamiento, solo a usuarios con acceso
 * al registro (el CompanyScope del registro impide ver fotos de otra empresa).
 */
class SurveyAdjuntoController extends Controller
{
    private const TIPOS_DE_IMAGEN = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    public function show(SurveyAdjunto $adjunto): StreamedResponse
    {
        abort_if($adjunto->registro === null || blank($adjunto->ruta), 404);

        $disco = Storage::disk(config('services.survey123.disco_fotos'));

        abort_unless($disco->exists($adjunto->ruta), 404);

        $tipo = in_array($adjunto->content_type, self::TIPOS_DE_IMAGEN, true)
            ? $adjunto->content_type
            : 'application/octet-stream';

        return $disco->response($adjunto->ruta, basename($adjunto->nombre), [
            'Content-Type' => $tipo,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
