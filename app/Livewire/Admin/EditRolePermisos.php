<?php

namespace App\Livewire\Admin;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

#[Layout('layouts.app')]
#[Title('Editar Rol')]
/**
 * Editor de permisos de un rol. Agrupa los permisos por módulo del sistema para facilitar la gestión.
 * El rol Administrador no puede renombrarse ni perder el permiso `roles.editar`.
 */
class EditRolePermisos extends Component
{
    public Role $role;

    public string $name = '';

    public string $backRoute = 'admin.roles';

    /** @var string[] Nombres de los permisos actualmente asignados al rol. */
    public array $selectedPermissions = [];

    public function mount(Role $role): void
    {
        abort_if(
            $role->name === 'Administrador'
            && ! auth()->user()?->is_superadmin
            && ! auth()->user()?->can('roles.editar'),
            403
        );

        $this->role = $role;
        $this->name = $role->name;
        $this->selectedPermissions = $role->permissions->pluck('name')->toArray();
    }

    /** Guarda nombre y permisos del rol, luego invalida la caché de Spatie para aplicar cambios de inmediato. */
    public function guardar(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:50'],
        ]);

        if ($this->role->name !== 'Administrador') {
            $this->role->update(['name' => $this->name]);
        }

        $this->role->syncPermissions($this->selectedPermissions);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        session()->flash('success', "Rol \"{$this->role->name}\" actualizado correctamente.");

        $this->redirect(route($this->backRoute), navigate: true);
    }

    /** @return array<string, mixed[]> */
    public function permisosPorModulo(): array
    {
        return Permission::all()
            ->groupBy(fn (Permission $p): string => $this->moduloDelPermiso($p->name))
            ->map(fn ($permisos, string $modulo) => [
                'modulo' => $modulo,
                'permisos' => $permisos->map(fn (Permission $p) => [
                    'name' => $p->name,
                    'description' => $p->description ?? $p->name,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Infiere el módulo al que pertenece un permiso a partir de su prefijo (p.ej. 'ordenes.ver' → 'Órdenes de Trabajo').
     */
    private function moduloDelPermiso(string $permiso): string
    {
        return match (true) {
            str_starts_with($permiso, 'ordenes.') => 'Órdenes de Trabajo',
            str_starts_with($permiso, 'mis-ordenes.') => 'Técnico',
            str_starts_with($permiso, 'auditorias.') => 'Auditorías',
            str_starts_with($permiso, 'informes.') => 'Informes',
            str_starts_with($permiso, 'legal.') => 'Legal',
            str_starts_with($permiso, 'evidencias.') => 'Recursos',
            str_starts_with($permiso, 'usuarios.') => 'Usuarios',
            str_starts_with($permiso, 'roles.') => 'Roles',
            str_starts_with($permiso, 'admin.') => 'Administración',
            default => 'Otros',
        };
    }

    public function render(): View
    {
        return view('livewire.admin.edit-role-permisos', [
            'permisosPorModulo' => $this->permisosPorModulo(),
            'esAdministrador' => $this->role->name === 'Administrador',
            'backRoute' => $this->backRoute,
        ]);
    }
}
