<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\personals;
use Illuminate\Database\Seeder;

class PersonalSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();

        personals::create([
            'tipo_documento' => 'CC',
            'numero_documento' => '0000',
            'nombres' => 'Administrador',
            'apellidos' => 'Sistema',
            'cargo' => 'Administrador del Sistema',
            'company_id' => $company->id,
        ]);

        personals::create([
            'tipo_documento' => 'CC',
            'numero_documento' => '0001',
            'nombres' => 'Auditor',
            'apellidos' => 'Sistema',
            'cargo' => 'Auditor',
            'company_id' => $company->id,
        ]);
    }
}
