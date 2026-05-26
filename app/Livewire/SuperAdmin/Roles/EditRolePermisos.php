<?php

namespace App\Livewire\SuperAdmin\Roles;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.superadmin')]
#[Title('Editar Rol')]
class EditRolePermisos extends \App\Livewire\Admin\EditRolePermisos
{
    public string $backRoute = 'superadmin.roles';
}
