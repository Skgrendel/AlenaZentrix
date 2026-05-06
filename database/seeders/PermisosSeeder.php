<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermisosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crea un permiso de ejemplo
        Permission::create([
            'name' => 'admin.index',
            'description'=>'Configuracion de Sistema',
            'category'=>'Administrador',
            'guard_name' => 'web',
            'estado' => 1, // Ajusta el valor según tus necesidades
        ]);
        Permission::create([
            'name' => 'admin.show',
            'description'=>'Ver Configuraciones',
            'category'=>'Administrador',
            'guard_name' => 'web',
            'estado' => 1, // Ajusta el valor según tus necesidades
        ]);
        Permission::create([
            'name' => 'admin.create',
            'description'=>'Crear Configuracion',
            'category'=>'Administrador',
            'guard_name' => 'web',
            'estado' => 1, // Ajusta el valor según tus necesidades
        ]);
        Permission::create([
            'name' => 'admin.edit',
            'description'=>'Editar Configuracion',
            'category'=>'Administrador',
            'guard_name' => 'web',
            'estado' => 1, // Ajusta el valor según tus necesidades
        ]);
        Permission::create([
            'name' => 'admin.destroy',
            'description'=>'Eliminar Configuracion',
            'category'=>'Administrador',
            'guard_name' => 'web',
            'estado' => 1, // Ajusta el valor según tus necesidades
        ]);
        Permission::create([
            'name' => 'coordi.index',
            'description'=>'Reportes',
            'category'=>'Coordinador',
            'guard_name' => 'web',
            'estado' => 1, // Ajusta el valor según tus necesidades
        ]);
        Permission::create([
            'name' => 'coordi.show',
            'description'=>'Revision de Reportes',
            'category'=>'Coordinador',
            'guard_name' => 'web',
            'estado' => 1, // Ajusta el valor según tus necesidades
        ]);
        Permission::create([
            'name' => 'coordi.create',
            'description'=>'Crear Reportes',
            'category'=>'Coordinador',
            'guard_name' => 'web',
            'estado' => 1, // Ajusta el valor según tus necesidades
        ]);
        Permission::create([
            'name' => 'coordi.edit',
            'description'=>'Editar Reportes',
            'category'=>'Coordinador',
            'guard_name' => 'web',
            'estado' => 1, // Ajusta el valor según tus necesidades
        ]);
        Permission::create([
            'name' => 'agente.index',
            'description'=>'Lecturas',
            'category'=>'Agentes',
            'guard_name' => 'web',
            'estado' => 1, // Ajusta el valor según tus necesidades
        ]);
        Permission::create([
            'name' => 'agente.show',
            'description'=>'Ver Lecturas',
            'category'=>'Agentes',
            'guard_name' => 'web',
            'estado' => 1, // Ajusta el valor según tus necesidades
        ]);
        Permission::create([
            'name' => 'agente.create',
            'description'=>'Crear Lecturas',
            'category'=>'Agentes',
            'guard_name' => 'web',
            'estado' => 1, // Ajusta el valor según tus necesidades
        ]);
        Permission::create([
            'name' => 'agente.edit',
            'description'=>'Editar Lecturas',
            'category'=>'Agentes',
            'guard_name' => 'web',
            'estado' => 1, // Ajusta el valor según tus necesidades
        ]);
        Permission::create([
            'name' => 'audit.index',
            'description'=>'Auditorias',
            'category'=>'Auditor',
            'guard_name' => 'web',
            'estado' => 1, // Ajusta el valor según tus necesidades
        ]);
        Permission::create([
            'name' => 'audit.show',
            'description'=>'Ver Auditorias',
            'category'=>'Auditor',
            'guard_name' => 'web',
            'estado' => 1, // Ajusta el valor según tus necesidades
        ]);
        Permission::create([
            'name' => 'audit.edit',
            'description'=>'editar Auditorias',
            'category'=>'Auditor',
            'guard_name' => 'web',
            'estado' => 1, // Ajusta el valor según tus necesidades
        ]);
        Permission::create([
            'name' => 'audit.create',
            'description'=>'Realizar Auditorias',
            'category'=>'Auditor',
            'guard_name' => 'web',
            'estado' => 1, // Ajusta el valor según tus necesidades
        ]);
    }
}
