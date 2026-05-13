<?php

namespace App\Livewire\SuperAdmin\Users;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.superadmin')]
#[Title('Usuarios')]
class UsersIndex extends Component
{
    public function render(): View
    {
        return view('livewire.super-admin.users.users-index');
    }
}
