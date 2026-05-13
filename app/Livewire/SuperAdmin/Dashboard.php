<?php

namespace App\Livewire\SuperAdmin;

use App\Models\Company;
use App\Models\OrdenJob;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.superadmin')]
#[Title('Panel Superadmin')]
class Dashboard extends Component
{
    #[Computed]
    public function totalEmpresas(): int
    {
        return Company::count();
    }

    #[Computed]
    public function totalUsuarios(): int
    {
        return User::withoutGlobalScopes()->where('is_superadmin', false)->count();
    }

    #[Computed]
    public function totalOrdenes(): int
    {
        return OrdenJob::withoutGlobalScopes()->count();
    }

    public function render(): View
    {
        return view('livewire.super-admin.dashboard');
    }
}
