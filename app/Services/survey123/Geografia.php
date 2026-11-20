<?php

namespace App\Services\survey123;

class Geografia
{
    /** Distancia en metros entre dos puntos (fórmula de Haversine). */
    public static function distanciaMetros(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $radioTierra = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return 2 * $radioTierra * asin(sqrt($a));
    }
}
