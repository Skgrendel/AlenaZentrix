<?php

namespace App\Livewire\SuperAdmin\Companies;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.superadmin')]
#[Title('Empresas')]
class CompaniesIndex extends Component
{
    public function render(): View
    {
        return view('livewire.super-admin.companies.companies-index');
    }
}
