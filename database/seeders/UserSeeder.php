<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\personals;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Superadmin — no company, no personal, acceso global
        User::create([
            'email' => 'superadmin@proderi.com',
            'personals_id' => null,
            'company_id' => null,
            'is_superadmin' => true,
            'password' => bcrypt('super.**'),
            'estado' => true,
            'email_verified_at' => now(),
        ]);

        $company = Company::first();

        // Administrador de empresa
        $personal = personals::withoutGlobalScopes()->where('numero_documento', '0000')->firstOrFail();
        $admin = User::create([
            'email' => 'admin@proderi.com',
            'personals_id' => $personal->id,
            'company_id' => $company->id,
            'password' => bcrypt('0000.**'),
            'estado' => true,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole(Role::where('name', 'Administrador')->firstOrFail());

    }
}
