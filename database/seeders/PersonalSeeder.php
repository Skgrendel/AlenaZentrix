<?php

namespace Database\Seeders;

use App\Models\personals;
use Illuminate\Database\Seeder;

class PersonalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Personal de Administrador Predeterminado
        $personal = personals::create([
            'tipo_documento' => 'CC',
            'numero_documento' => '0000',
            'nombres' => 'Administrador',
            'apellidos' => 'Sistema',
            'cargo' => 'Administrador del Sistema',
        ]);

        // Personal de Auditor Predeterminado
        personals::create([
            'tipo_documento' => 'CC',
            'numero_documento' => '0001',
            'nombres' => 'Auditor',
            'apellidos' => 'Sistema',
            'cargo' => 'Auditor',
        ]);

    }
}
