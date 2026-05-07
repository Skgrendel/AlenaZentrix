<?php

namespace Database\Seeders;

use App\Models\ResultadoReporte;
use Illuminate\Database\Seeder;

class ResultadoReporteSeeder extends Seeder
{
    public function run(): void
    {
        $resultados = [
            'Anomalía Confirmada',
            'Sin Anomalía',
            'Positivo',
            'Negativo',
            'Pendiente de Verificación',
        ];

        foreach ($resultados as $nombre) {
            ResultadoReporte::create(['nombre' => $nombre]);
        }
    }
}
