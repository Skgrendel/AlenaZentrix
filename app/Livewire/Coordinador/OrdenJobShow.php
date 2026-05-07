<?php

namespace App\Livewire\Coordinador;

use App\Models\OrdenJob;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Ver Orden')]
/**
 * Vista de solo lectura de una orden de trabajo en estado Pendiente, para que el coordinador
 * pueda revisarla antes de asignarla o editarla.
 */
class OrdenJobShow extends Component
{
    public OrdenJob $orden;

    public function mount(OrdenJob $orden): void
    {
        $this->orden = $orden->load('personal');
    }

    /**
     * Genera las URLs firmadas/proxy de las fotos almacenadas en S3 para mostrarlas en la vista.
     *
     * @return array<int, string>
     */
    public function fotoUrls(): array
    {
        return collect($this->orden->fotos ?? [])
            ->map(fn (string $path) => route('operaciones.foto', ['path' => $path]))
            ->all();
    }

    public function render(): View
    {
        return view('livewire.coordinador.orden-job-show', [
            'fotoUrls' => $this->fotoUrls(),
        ]);
    }
}
