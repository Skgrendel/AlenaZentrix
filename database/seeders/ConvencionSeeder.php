<?php

namespace Database\Seeders;

use App\Models\Convencion;
use Illuminate\Database\Seeder;

class ConvencionSeeder extends Seeder
{
    public function run(): void
    {
        $convenciones = [
            'Seguimiento de Sospechoso',
            'Vigilancia de Punto Fijo',
            'Inteligencia de Fuentes Abiertas',
            'Entrevista a Testigo',
            'Revisión Documental',
            'Verificación en Campo',
            'Operación Encubierta',
            'Coordinación con Autoridades',
        ];

        foreach ($convenciones as $nombre) {
            Convencion::create(['nombre' => $nombre]);
        }
    }
}
