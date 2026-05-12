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
     * Genera las URLs firmadas/proxy de los documentos almacenados en S3 para mostrarlos en la vista.
     *
     * @return array<int, string>
     */
    public function documentoUrls(): array
    {
        return collect($this->orden->documentos ?? [])
            ->map(fn (string $path) => route('operaciones.foto', ['path' => $path]))
            ->all();
    }

    public function render(): View
    {
        return view('livewire.coordinador.orden-job-show', [
            'documentoUrls' => $this->documentoUrls(),
        ]);
    }
}
