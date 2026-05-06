<?php

namespace Database\Seeders;

use App\Models\personals;
use App\Models\reportes;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ReportesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        personals::factory(15)->create();
        reportes::factory(100)->create();
    }
}
