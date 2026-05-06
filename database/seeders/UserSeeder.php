<?php

namespace Database\Seeders;

use App\Models\personals;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Usuario de Administrador Predeterminado
        $personal = personals::where('numero_documento', '0000')->firstOrFail();

        $user = User::create([
            'email' => 'admin@proderi.com',
            'personals_id' => $personal->id,
            'password' => bcrypt('0000.**'),
            'estado' => 1,
            'email_verified_at' => now(),
        ]);

        $Role = Role::where('name', 'Administrador')->firstOrFail();
        $user->assignRole($Role);

        // Usuario Auditor Predeterminado
        $personalAuditor = personals::where('numero_documento', '0001')->firstOrFail();

        $userAuditor = User::create([
            'email' => 'auditor@proderi.com',
            'personals_id' => $personalAuditor->id,
            'password' => bcrypt('0001.**'),
            'estado' => 1,
            'email_verified_at' => now(),
        ]);

        $roleAuditor = Role::where('name', 'Auditor')->firstOrFail();
        $userAuditor->assignRole($roleAuditor);
    }
}
