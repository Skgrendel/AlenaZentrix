<?php

namespace App\Livewire\Tecnico;

use App\Enums\EstadoOrden;
use App\Mail\ReporteEnRevision;
use App\Models\comercio;
use App\Models\MarcaMedidor;
use App\Models\MarcaRegulador;
use App\Models\OrdenJob;
use App\Models\reportes;
use App\Models\TipoAnomalia;
use App\Models\TipoComercio;
use App\Models\TipoImposibilidad;
use App\Models\TipoRegulador;
use App\Models\ubicacion;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Crear Reporte')]
class ReporteCreate extends Component implements HasForms
{
    use InteractsWithForms;

    public OrdenJob $orden;

    public ?string $latitud = null;

    public ?string $longitud = null;

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(OrdenJob $orden): void
    {
        abort_unless(
            auth()->user()->can('ordenes.ver') ||
            $orden->personals_id === auth()->user()->personals_id,
            403
        );

        $this->orden = $orden;
        $this->form->fill();
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Datos de la Visita')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('lectura')
                            ->label('Lectura del Medidor')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('numero_medidor')
                            ->label('Número de Medidor')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('nombre_comercio')
                            ->label('Nombre del Comercio')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Forms\Components\Select::make('tipo_comercio_id')
                            ->label('Tipo de Comercio')
                            ->options(
                                TipoComercio::query()
                                    ->where('activo', true)
                                    ->orderBy('nombre')
                                    ->pluck('nombre', 'id')
                            )
                            ->searchable()
                            ->required()
                            ->columnSpanFull(),
                        Forms\Components\Select::make('anomalia')
                            ->label('Anomalías')
                            ->options(
                                TipoAnomalia::query()
                                    ->where('activo', true)
                                    ->orderBy('nombre')
                                    ->pluck('nombre', 'id')
                            )
                            ->multiple()
                            ->searchable()
                            ->columnSpanFull(),
                    ]),

                Section::make('Clasificación')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('tipo_imposibilidad_id')
                            ->label('Tipo de Imposibilidad')
                            ->options(
                                TipoImposibilidad::query()
                                    ->where('activo', true)
                                    ->orderBy('nombre')
                                    ->pluck('nombre', 'id')
                            )
                            ->searchable()
                            ->placeholder('Ninguna'),
                        Forms\Components\Select::make('tipo_regulador_id')
                            ->label('Tipo de Regulador')
                            ->options(
                                TipoRegulador::query()
                                    ->where('activo', true)
                                    ->orderBy('nombre')
                                    ->pluck('nombre', 'id')
                            )
                            ->searchable()
                            ->placeholder('Ninguno'),
                        Forms\Components\Select::make('marca_medidor_id')
                            ->label('Marca del Medidor')
                            ->options(
                                MarcaMedidor::query()
                                    ->where('activo', true)
                                    ->orderBy('nombre')
                                    ->pluck('nombre', 'id')
                            )
                            ->searchable()
                            ->placeholder('Ninguna'),
                        Forms\Components\Select::make('marca_regulador_id')
                            ->label('Marca del Regulador')
                            ->options(
                                MarcaRegulador::query()
                                    ->where('activo', true)
                                    ->orderBy('nombre')
                                    ->pluck('nombre', 'id')
                            )
                            ->searchable()
                            ->placeholder('Ninguna'),
                        Forms\Components\TextInput::make('cau')
                            ->label('CAU')
                            ->maxLength(255),
                    ]),

                Section::make('Evidencias')
                    ->schema([
                        Forms\Components\FileUpload::make('imagenes')
                            ->label('Imágenes')
                            ->disk('s3')
                            ->directory('reportes/'.$this->orden->numero_orden)
                            ->multiple()
                            ->image()
                            ->imagePreviewHeight('100')
                            ->maxFiles(10)
                            ->maxSize(5120)
                            ->reorderable()
                            ->panelLayout('grid')
                            ->extraInputAttributes(['capture' => 'environment'])
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('video')
                            ->label('URL del Video')
                            ->url()
                            ->maxLength(500),
                    ]),

                Section::make('Observaciones')
                    ->schema([
                        Forms\Components\Textarea::make('observaciones')
                            ->label('Observaciones')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $data = $this->form->getState();

        $comercioRecord = comercio::create([
            'nombre_comercio' => $data['nombre_comercio'],
            'tipo_comercio_id' => $data['tipo_comercio_id'],
        ]);

        unset($data['nombre_comercio'], $data['tipo_comercio_id']);

        $ubicacionRecord = ubicacion::create([
            'latitud' => $this->latitud,
            'longitud' => $this->longitud,
            'direccion' => $this->orden->direccion,
        ]);

        reportes::create([
            ...$data,
            'orden_job_id' => $this->orden->id,
            'numero_orden' => $this->orden->numero_orden,
            'ubicacions_id' => $ubicacionRecord->id,
            'comercios_id' => $comercioRecord->id,
        ]);

        $this->orden->update(['estado' => EstadoOrden::EnRevision]);

        $this->notificarCoordinador();

        $this->redirect(route('tecnico.mis-ordenes'), navigate: true);
    }

    protected function notificarCoordinador(): void
    {
        $email = $this->orden->creadoPor?->email;
        $tecnico = $this->orden->personal;

        if (! $email || ! $tecnico) {
            return;
        }

        try {
            Mail::to($email)->send(new ReporteEnRevision($this->orden, $tecnico));
        } catch (\Exception $e) {
            Log::error('No se pudo notificar al coordinador sobre el reporte.', [
                'orden' => $this->orden->numero_orden,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function render(): View
    {
        return view('livewire.tecnico.reporte-create');
    }
}
