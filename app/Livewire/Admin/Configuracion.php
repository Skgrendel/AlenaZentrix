<?php

namespace App\Livewire\Admin;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Configuración')]
/**
 * Página de configuración general del sistema para administradores.
 */
class Configuracion extends Component
{
    public function render(): View
    {
        return view('livewire.admin.configuracion');
    }
}
