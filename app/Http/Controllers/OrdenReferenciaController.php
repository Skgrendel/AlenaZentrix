<?php

namespace App\Http\Controllers;

use App\Models\OrdenJob;
use App\Services\survey123\FachadaVerificada;
use App\Services\survey123\MapaDeUbicacion;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Entrega al auditor de campo las dos imágenes con las que ubica el predio: la foto de fachada (solo si la IA
 * confirmó que es una fachada) y el mapa del lugar. Solo de órdenes originadas en un registro de suspensión, y solo a quien puede ver la orden.
 */
class OrdenReferenciaController extends Controller
{
    public function fachada(OrdenJob $orden, FachadaVerificada $fachada): StreamedResponse
    {
        $this->autorizar($orden);

        $foto = $fachada->para($orden->surveyRegistro);

        abort_if($foto === null, 404);

        return $this->enviar($foto->ruta, $foto->nombre, $foto->content_type);
    }

    public function mapa(OrdenJob $orden, MapaDeUbicacion $mapa): StreamedResponse
    {
        $this->autorizar($orden);

        $ruta = $mapa->ruta($orden->surveyRegistro);

        abort_if($ruta === null, 404);

        return $this->enviar($ruta, 'mapa.png', 'image/png');
    }

    private function autorizar(OrdenJob $orden): void
    {
        $usuario = auth()->user();

        abort_if($orden->survey_registro_id === null || $orden->surveyRegistro === null, 404);

        abort_unless(
            $usuario->can('ordenes.ver') || ($usuario->personals_id !== null && $usuario->personals_id === $orden->personals_id),
            403,
        );
    }

    private function enviar(string $ruta, string $nombre, ?string $tipo): StreamedResponse
    {
        $disco = Storage::disk(config('services.survey123.disco_fotos'));

        abort_unless($disco->exists($ruta), 404);

        return $disco->response($ruta, basename($nombre), [
            'Content-Type' => in_array($tipo, ['image/jpeg', 'image/png', 'image/webp'], true) ? $tipo : 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
