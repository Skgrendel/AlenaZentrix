<?php

namespace Database\Seeders;

use App\Models\TipologiaOrden;
use Illuminate\Database\Seeder;

class TipologiaOrdenSeeder extends Seeder
{
    public function run(): void
    {
        $tipologias = [
            'Entrevistas',
            'Impulsos en Fiscalía',
            'Capturas en Flagrancia',
            'Allanamientos',
            'Desarticulación de Red',
            'Operación Conjunta',
            'Orden Judicial',
        ];

        foreach ($tipologias as $nombre) {
            TipologiaOrden::create(['nombre' => $nombre]);
        }
    }
}
