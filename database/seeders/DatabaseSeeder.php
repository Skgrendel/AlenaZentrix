<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PermissionsSeeder::class);
        $this->call(CompanySeeder::class);  // CompanyObserver seeds catalogs automatically on created
        $this->call(PersonalSeeder::class);
        $this->call(UserSeeder::class);
    }
}
