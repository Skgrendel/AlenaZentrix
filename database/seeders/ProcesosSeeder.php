<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Proceso;
use Illuminate\Database\Seeder;

class ProcesosSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();

        if (! $company) {
            return;
        }

        $procesos = [
            'Servicios Periciales',
            'Construcciones y Trabajos Varios',
        ];

        foreach ($procesos as $nombre) {
            Proceso::withoutGlobalScopes()->firstOrCreate(
                ['nombre' => $nombre, 'company_id' => $company->id],
                ['activo' => true]
            );
        }
    }
}
