<?php

namespace App\Services\survey123;

use App\Models\SurveyRegistro;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Imagen del mapa del lugar de la visita, tomada del servicio público de mapas de ArcGIS y guardada junto
 * a las fotos del registro. Se genera la primera vez que se pide y se reutiliza después.
 */
class MapaDeUbicacion
{
    private const URL = 'https://services.arcgisonline.com/arcgis/rest/services/World_Street_Map/MapServer/export';

    private const ANCHO = 640;

    private const ALTO = 400;

    /** Metros que cubre el ancho de la imagen. */
    private const METROS_DE_ANCHO = 400;

    private const RADIO_MERCATOR = 6378137.0;

    /** Ruta de la imagen en el disco de fotos, o null si el registro no tiene ubicación o el servicio no respondió. */
    public function ruta(SurveyRegistro $registro): ?string
    {
        if ($registro->latitud === null || $registro->longitud === null) {
            return null;
        }

        $disco = Storage::disk(config('services.survey123.disco_fotos'));
        $ruta = "survey123/mapas/{$registro->company_id}/{$registro->id}.png";

        if ($disco->exists($ruta)) {
            return $ruta;
        }

        try {
            $imagen = $this->generar((float) $registro->latitud, (float) $registro->longitud);
        } catch (Throwable $e) {
            Log::warning('No se pudo traer el mapa de ubicación.', ['registro' => $registro->id, 'error' => $e->getMessage()]);

            return null;
        }

        return $disco->put($ruta, $imagen) === false ? null : $ruta;
    }

    private function generar(float $latitud, float $longitud): string
    {
        [$x, $y] = $this->aMercator($latitud, $longitud);
        $mitadX = self::METROS_DE_ANCHO / 2;
        $mitadY = $mitadX * self::ALTO / self::ANCHO;

        $respuesta = Http::timeout(10)->get(self::URL, [
            'bbox' => implode(',', [$x - $mitadX, $y - $mitadY, $x + $mitadX, $y + $mitadY]),
            'bboxSR' => 3857,
            'imageSR' => 3857,
            'size' => self::ANCHO.','.self::ALTO,
            'format' => 'png',
            'f' => 'image',
        ]);

        if ($respuesta->failed() || ! str_starts_with((string) $respuesta->header('Content-Type'), 'image/')) {
            throw new \RuntimeException('El servicio de mapas respondió '.$respuesta->status().'.');
        }

        return $this->marcar($respuesta->body());
    }

    /** Pone un círculo rojo en el centro (el punto de la visita) y la atribución del proveedor del mapa. */
    private function marcar(string $contenido): string
    {
        $imagen = @imagecreatefromstring($contenido);

        if ($imagen === false) {
            throw new \RuntimeException('La imagen del mapa no se pudo leer.');
        }

        $centroX = intdiv(imagesx($imagen), 2);
        $centroY = intdiv(imagesy($imagen), 2);
        $blanco = imagecolorallocate($imagen, 255, 255, 255);
        $rojo = imagecolorallocate($imagen, 227, 6, 19);

        imagefilledellipse($imagen, $centroX, $centroY, 30, 30, $blanco);
        imagefilledellipse($imagen, $centroX, $centroY, 20, 20, $rojo);
        imagefilledellipse($imagen, $centroX, $centroY, 6, 6, $blanco);

        imagefilledrectangle($imagen, 0, imagesy($imagen) - 14, 150, imagesy($imagen), $blanco);
        imagestring($imagen, 2, 4, imagesy($imagen) - 14, 'Mapa: Esri, ArcGIS', imagecolorallocate($imagen, 80, 80, 80));

        ob_start();
        imagepng($imagen);

        return (string) ob_get_clean();
    }

    /** @return array{0: float, 1: float} metros X e Y en Web Mercator (EPSG:3857) */
    private function aMercator(float $latitud, float $longitud): array
    {
        $x = self::RADIO_MERCATOR * deg2rad($longitud);
        $y = self::RADIO_MERCATOR * log(tan(M_PI / 4 + deg2rad($latitud) / 2));

        return [$x, $y];
    }
}
