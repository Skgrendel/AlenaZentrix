<?php

namespace App\Livewire\Coordinador\ConsultaGis;

use App\Services\coordinador\DataGisServices;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
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

    public function gisResultado(Schema $schema): Schema
    {
        $r = $this->resultado ?? [];
        $ubicacion = $r['ubicacion'] ?? [];
        $servicio = $r['servicio'] ?? [];
        $medidor = $r['medidor'] ?? [];

        $anilladoRaw = $servicio['anillado'] ?? null;
        $anillado = match (strtoupper((string) $anilladoRaw)) {
            'Y' => 'Sí',
            'N' => 'No',
            default => $anilladoRaw,
        };

        return $schema
            ->components([
                Grid::make(2)
                    ->schema([
                        Section::make('Ubicación')
                            ->schema([
                                TextEntry::make('municipio')
                                    ->label('Municipio')
                                    ->state($ubicacion['municipio'] ?? null)
                                    ->placeholder('—'),
                                TextEntry::make('direccion')
                                    ->label('Dirección')
                                    ->state($ubicacion['direccion'] ?? null)
                                    ->placeholder('—'),
                                TextEntry::make('barrio')
                                    ->label('Barrio')
                                    ->state($ubicacion['barrio'] ?? null)
                                    ->placeholder('—'),
                                TextEntry::make('tag')
                                    ->label('TAG')
                                    ->state($ubicacion['tag'] ?? null)
                                    ->placeholder('—'),
                            ]),
                        Section::make('Servicio')
                            ->schema([
                                TextEntry::make('estado')
                                    ->label('Estado')
                                    ->state($servicio['estado'] ?? null)
                                    ->badge()
                                    ->color(fn ($state) => match (strtoupper((string) $state)) {
                                        'ACTIVO' => 'success',
                                        'SUSPENDIDO' => 'warning',
                                        'CORTADO' => 'danger',
                                        default => 'gray',
                                    }),
                                TextEntry::make('estado_corte')
                                    ->label('Estado de Corte')
                                    ->state($servicio['estado_corte'] ?? null)
                                    ->placeholder('—'),
                                TextEntry::make('descripcion')
                                    ->label('Descripción')
                                    ->state($servicio['descripcion'] ?? null)
                                    ->placeholder('—'),
                                TextEntry::make('categoria')
                                    ->label('Categoría')
                                    ->state($servicio['categoria'] ?? null)
                                    ->placeholder('—'),
                                Grid::make(3)
                                    ->schema([
                                        TextEntry::make('estrato')
                                            ->label('Estrato')
                                            ->state($servicio['estrato'] ?? null)
                                            ->placeholder('—'),
                                        TextEntry::make('ciclo')
                                            ->label('Ciclo')
                                            ->state($servicio['ciclo'] ?? null)
                                            ->placeholder('—'),
                                        TextEntry::make('anillado')
                                            ->label('Anillado')
                                            ->state($anillado)
                                            ->placeholder('—'),
                                    ]),
                            ]),
                    ]),
                Section::make('Medidor')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('serie_actual')
                            ->label('Serie Actual')
                            ->state($medidor['serie_actual'] ?? null)
                            ->placeholder('—')
                            ->copyable(),
                        TextEntry::make('serie_anterior')
                            ->label('Serie Anterior')
                            ->state($medidor['serie_anterior'] ?? null)
                            ->placeholder('—')
                            ->copyable(),
                        TextEntry::make('fecha_anterior')
                            ->label('Fecha Anterior')
                            ->state($medidor['fecha_anterior'] ?? null)
                            ->placeholder('—'),
                    ]),
            ]);
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
