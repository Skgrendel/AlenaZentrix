<?php

namespace Database\Seeders;

use App\Models\OrdenJob;
use App\Models\personals;
use Illuminate\Database\Seeder;

class OrdenJobsTestSeeder extends Seeder
{
    public function run(): void
    {
        $personal = personals::where('numero_documento', '0000')->first();

        $ordenes = [
            [
                'numero_orden' => 'OJ-2024-001',
                'contrato'     => 'CT-100001',
                'cliente'      => 'Juan García',
                'direccion'    => 'Calle 10 # 5-23',
                'barrio'       => 'Centro',
                'medidor'      => 'MED-001',
                'tipo_medidor' => 'G-4',
                'ciclo'        => '01',
                'prioridad'    => 'media',
                'estado'       => 'pendiente',
            ],
            [
                'numero_orden' => 'OJ-2024-002',
                'contrato'     => 'CT-100002',
                'cliente'      => 'María López',
                'direccion'    => 'Carrera 15 # 8-45',
                'barrio'       => 'El Prado',
                'medidor'      => 'MED-002',
                'tipo_medidor' => 'G-6',
                'ciclo'        => '02',
                'prioridad'    => 'alta',
                'estado'       => 'asignada',
            ],
            [
                'numero_orden' => 'OJ-2024-003',
                'contrato'     => 'CT-100003',
                'cliente'      => 'Carlos Pérez',
                'direccion'    => 'Avenida 30 # 12-10',
                'barrio'       => 'Bello Horizonte',
                'medidor'      => 'MED-003',
                'tipo_medidor' => 'G-2.5',
                'ciclo'        => '01',
                'prioridad'    => 'urgente',
                'estado'       => 'en_progreso',
            ],
        ];

        foreach ($ordenes as $data) {
            OrdenJob::create(array_merge($data, [
                'personals_id' => $personal?->id,
            ]));
        }
    }
}
