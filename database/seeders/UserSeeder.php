<?php

namespace Database\Seeders;

use App\Models\personals;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
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
        ]);

        $Role = Role::where('name', 'Administrador')->firstOrFail();
        $user->assignRole($Role);


    }
}
