<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CatalogosSeeder::class);
        $this->call(TipoNotificacionAlertaSeeder::class);
        $this->call(ConvencionSeeder::class);
        $this->call(TipologiaOrdenSeeder::class);
        $this->call(ResultadoReporteSeeder::class);
        $this->call(PermissionsSeeder::class);
        $this->call(CompanySeeder::class);
        $this->call(PersonalSeeder::class);
        $this->call(UserSeeder::class);
    }
}
