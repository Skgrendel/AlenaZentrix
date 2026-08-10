<?php

namespace App\Livewire\Tecnico;

use App\Models\OrdenJob;
use App\Support\ArchivoUrl;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Ver Orden')]
/**
 * Vista de solo lectura de una orden de trabajo para que el técnico pueda ver
 * los detalles antes de iniciar o mientras está trabajando en ella.
 */
class OrdenShow extends Component
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
            ->map(fn (string $path) => ArchivoUrl::temporal($path))
            ->all();
    }

    public function render(): View
    {
        return view('livewire.tecnico.orden-show', [
            'documentoUrls' => $this->documentoUrls(),
        ]);
    }
}
