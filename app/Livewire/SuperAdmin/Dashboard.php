<?php

namespace App\Livewire\SuperAdmin;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.superadmin')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    public function render(): View
    {
        return view('livewire.super-admin.dashboard');
    }
}
