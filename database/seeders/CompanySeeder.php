<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        Company::create([
            'nombre' => 'Surtigas S.A. E.S.P.',
            'logo' => 'https://www.surtigas.com.co/imagenes/logo_color_surtigas.svg',
            'activo' => true,
        ]);
    }
}
