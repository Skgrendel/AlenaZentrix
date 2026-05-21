<?php

namespace App\Livewire\Coordinador\ConsultaGis;

use App\Services\coordinador\DataGisServices;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Consulta GIS')]
class ConsultaGisIndex extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    /** @var array<string, mixed> */
    public array $data = [];

    /** @var array<string, mixed>|null */
    public ?array $resultado = null;

    public ?string $error = null;

    public bool $cargando = false;

    public function mount(): void
    {
        abort_unless(auth()->user()?->company?->gis_habilitado, 403);

        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('contrato')
                    ->label('Número de Contrato')
                    ->placeholder('Ej: 123456789')
                    ->required()
                    ->maxLength(50)
                    ->autofocus(),
            ])
            ->statePath('data');
    }

    public function buscar(): void
    {
        $state = $this->form->getState();
        $contrato = $state['contrato'];

        $this->resultado = null;
        $this->error = null;
        $this->cargando = true;

        $resultado = app(DataGisServices::class)->consultarContrato($contrato);

        $this->cargando = false;

        if (isset($resultado['error'])) {
            $this->error = $resultado['error'];

            return;
        }

        $this->resultado = $resultado;
    }

    public function limpiar(): void
    {
        $this->resultado = null;
        $this->error = null;
        $this->form->fill();
    }

    public function render(): View
    {
        return view('livewire.coordinador.consulta-gis.consulta-gis-index');
    }
}
