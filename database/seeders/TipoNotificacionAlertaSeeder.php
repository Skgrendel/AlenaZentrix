<?php

namespace Database\Seeders;

use App\Models\TipoNotificacionAlerta;
use Illuminate\Database\Seeder;

class TipoNotificacionAlertaSeeder extends Seeder
{
    public function run(): void
    {
        $alertas = [
            'Sin Alertas',
            'Exceso de Capacidad',
            'Retro Flujo',
            'Flujo cero',
            'Posible fuga interna',
            'Fuga Pequeña Const 7 Días',
            'Batería Baja',
            'CAU 01',
            'CAU 02',
            'CAU 03',
            'CAU 04',
        ];

        foreach ($alertas as $nombre) {
            TipoNotificacionAlerta::firstOrCreate(['nombre' => $nombre], ['activo' => true]);
        }
    }
}
