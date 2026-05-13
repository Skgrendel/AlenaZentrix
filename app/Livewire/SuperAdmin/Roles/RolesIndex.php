<?php

namespace App\Livewire\SuperAdmin\Roles;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.superadmin')]
#[Title('Roles y Permisos')]
class RolesIndex extends Component
{
    public function render(): View
    {
        return view('livewire.super-admin.roles.roles-index');
    }
}
