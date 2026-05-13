<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Ejecutar en base de datos existente: php artisan db:seed --class=PermissionsSeeder
 * Idempotente: se puede correr múltiples veces sin duplicar datos.
 */
class PermissionsSeeder extends Seeder
{
    /** @var array<string, array<string, string>> */
    private array $permisos = [
        'ordenes.ver' => ['label' => 'Ver listado y detalle de órdenes',                  'modulo' => 'Órdenes de Trabajo'],
        'ordenes.crear' => ['label' => 'Crear nuevas órdenes',                              'modulo' => 'Órdenes de Trabajo'],
        'ordenes.editar' => ['label' => 'Editar órdenes existentes',                         'modulo' => 'Órdenes de Trabajo'],
        'ordenes.eliminar' => ['label' => 'Eliminar órdenes',                                  'modulo' => 'Órdenes de Trabajo'],
        'ordenes.revisar' => ['label' => 'Revisar reporte del técnico (aprobar/rechazar)',     'modulo' => 'Órdenes de Trabajo'],
        'mis-ordenes.ver' => ['label' => 'Ver órdenes propias asignadas',                     'modulo' => 'Técnico'],
        'mis-ordenes.reportar' => ['label' => 'Crear y editar reportes de sus órdenes',            'modulo' => 'Técnico'],
        'auditorias.ver' => ['label' => 'Ver listado y resultados de auditorías',             'modulo' => 'Auditorías'],
        'auditorias.registrar' => ['label' => 'Registrar resultado de una auditoría',              'modulo' => 'Auditorías'],
        'informes.ordenes' => ['label' => 'Ver/descargar informe de órdenes asignadas',        'modulo' => 'Informes'],
        'informes.tecnico' => ['label' => 'Ver/descargar informe de respuestas del técnico',   'modulo' => 'Informes'],
        'informes.auditorias' => ['label' => 'Ver/descargar informe de auditorías',               'modulo' => 'Informes'],
        'informes' => ['label' => 'Descargar informe individual de inspección por Servicio', 'modulo' => 'Informes'],
        'mapa.ver' => ['label' => 'Visualizar mapa de ubicaciones de reportes',           'modulo' => 'Mapa'],
        'legal.ver' => ['label' => 'Acceso al módulo Legal',                            'modulo' => 'Legal'],
        'evidencias.ver' => ['label' => 'Ver fotos y documentos',                            'modulo' => 'Recursos'],
        'usuarios.ver' => ['label' => 'Ver listado de usuarios',                           'modulo' => 'Usuarios'],
        'usuarios.crear' => ['label' => 'Crear nuevos usuarios',                             'modulo' => 'Usuarios'],
        'usuarios.editar' => ['label' => 'Editar datos de usuarios existentes',               'modulo' => 'Usuarios'],
        'usuarios.eliminar' => ['label' => 'Eliminar usuarios',                                 'modulo' => 'Usuarios'],
        'usuarios.asignar-roles' => ['label' => 'Asignar y quitar roles a usuarios',                 'modulo' => 'Usuarios'],
        'roles.ver' => ['label' => 'Ver listado de roles y sus permisos',               'modulo' => 'Roles'],
        'roles.crear' => ['label' => 'Crear nuevos roles',                                'modulo' => 'Roles'],
        'roles.editar' => ['label' => 'Editar roles y asignarles permisos',                'modulo' => 'Roles'],
        'roles.eliminar' => ['label' => 'Eliminar roles',                                    'modulo' => 'Roles'],
        'admin.configuracion' => ['label' => 'Gestionar configuración del sistema',               'modulo' => 'Administración'],
        'empresa.admin' => ['label' => 'Gestionar configuración de la propia empresa',          'modulo' => 'Administración'],
    ];

    /** @var array<string, string[]> */
    private array $roles = [
        'Administrador' => [
            'ordenes.ver', 'ordenes.crear', 'ordenes.editar', 'ordenes.eliminar', 'ordenes.revisar',
            'mis-ordenes.ver', 'mis-ordenes.reportar',
            'auditorias.ver', 'auditorias.registrar',
            'informes.ordenes', 'informes.tecnico', 'informes.auditorias', 'informes',
            'mapa.ver',
            'legal.ver',
            'evidencias.ver',
            'usuarios.ver', 'usuarios.crear', 'usuarios.editar', 'usuarios.eliminar', 'usuarios.asignar-roles',
            'admin.configuracion',
            'empresa.admin',
        ],

        'Coordinador' => [
            'ordenes.ver', 'ordenes.crear', 'ordenes.editar', 'ordenes.eliminar', 'ordenes.revisar',
            'auditorias.ver',
            'informes.ordenes', 'informes',
            'mapa.ver',
            'evidencias.ver',
        ],

        'Lector' => [
            'mis-ordenes.ver', 'mis-ordenes.reportar',
            'informes.tecnico',
            'evidencias.ver',
        ],

        'Auditor' => [
            'auditorias.ver', 'auditorias.registrar',
            'informes.auditorias',
            'evidencias.ver',
        ],

        'Legal' => [
            'ordenes.ver',
            'auditorias.ver',
            'informes.ordenes', 'informes.auditorias',
            'legal.ver',
            'evidencias.ver',
        ],
    ];

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ($this->permisos as $name => $meta) {
            Permission::updateOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['description' => $meta['label'], 'category' => $meta['modulo']]
            );
        }

        Permission::whereNotIn('name', array_keys($this->permisos))->delete();

        $todosLosPermisos = Permission::all();

        foreach ($this->roles as $roleName => $permisoNames) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

            if ($permisoNames === ['*']) {
                $role->syncPermissions($todosLosPermisos);
            } else {
                $role->syncPermissions(
                    Permission::whereIn('name', $permisoNames)->get()
                );
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->command->info('✓ '.count($this->permisos).' permisos y '.count($this->roles).' roles configurados.');
    }
}
