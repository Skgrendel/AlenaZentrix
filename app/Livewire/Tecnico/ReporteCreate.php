<?php

namespace App\Livewire\Tecnico;

use App\Enums\EstadoOrden;
use App\Mail\IncidenciaDetectada;
use App\Mail\ReporteEnRevision;
use App\Models\comercio;
use App\Models\CorreoAlerta;
use App\Models\MarcaMedidor;
use App\Models\MarcaRegulador;
use App\Models\OrdenJob;
use App\Models\reportes;
use App\Models\TipoAnomalia;
use App\Models\TipoComercio;
use App\Models\TipoImposibilidad;
use App\Models\TipoNotificacionAlerta;
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
                Section::make('1. Datos de la Orden')
                    ->icon('heroicon-o-document-text')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('nombre_comercio')
                            ->label('Nombre del Comercio')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('tipo_comercio_id')
                            ->label('Tipo de Comercio Encontrado')
                            ->options(
                                TipoComercio::query()
                                    ->where('activo', true)
                                    ->orderBy('nombre')
                                    ->pluck('nombre', 'id')
                            )
                            ->searchable()
                            ->required(),
                    ]),

                Section::make('2. Información del Medidor')
                    ->icon('heroicon-o-eye')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('numero_medidor')
                            ->label('Número de Medidor Encontrado')
                            ->helperText('Ingresa el número del medidor que encontraste en el sitio')
                            ->maxLength(255),
                        Forms\Components\Toggle::make('coincide_medidor')
                            ->label('¿Coincide con el medidor asignado?')
                            ->helperText('Activo = Sí coincide · Inactivo = No coincide'),
                        Forms\Components\TextInput::make('lectura')
                            ->label('Lectura del Medidor')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('marca_regulador_id')
                            ->label('Descripción del Medidor')
                            ->options(
                                MarcaRegulador::query()
                                    ->where('activo', true)
                                    ->orderBy('nombre')
                                    ->pluck('nombre', 'id')
                            )
                            ->searchable()
                            ->placeholder('Seleccionar'),
                    ]),

                Section::make('3. Configuración Técnica')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('tipo_regulador_id')
                            ->label('Tipo de Presión')
                            ->options(
                                TipoRegulador::query()
                                    ->where('activo', true)
                                    ->orderBy('nombre')
                                    ->pluck('nombre', 'id')
                            )
                            ->searchable()
                            ->placeholder('Seleccionar'),
                        Forms\Components\Select::make('marca_medidor_id')
                            ->label('Marca del Medidor')
                            ->options(
                                MarcaMedidor::query()
                                    ->where('activo', true)
                                    ->orderBy('nombre')
                                    ->pluck('nombre', 'id')
                            )
                            ->searchable()
                            ->placeholder('Seleccionar'),
                        Forms\Components\Select::make('tipo_notificacion_alerta_id')
                            ->label('Notificación de Alertas')
                            ->options(
                                TipoNotificacionAlerta::query()
                                    ->where('activo', true)
                                    ->orderBy('nombre')
                                    ->pluck('nombre', 'id')
                            )
                            ->searchable()
                            ->placeholder('Sin Alertas')
                            ->columnSpanFull(),
                    ]),

                Section::make('4. Detección de Anomalías')
                    ->icon('heroicon-o-magnifying-glass')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('anomalia')
                            ->label('Anomalías Detectadas')
                            ->options(
                                TipoAnomalia::query()
                                    ->where('activo', true)
                                    ->orderBy('nombre')
                                    ->pluck('nombre', 'id')
                            )
                            ->multiple()
                            ->searchable()
                            ->placeholder('Seleccione las anomalías'),
                        Forms\Components\Select::make('tipo_imposibilidad_id')
                            ->label('¿Hubo Imposibilidad?')
                            ->options(
                                TipoImposibilidad::query()
                                    ->where('activo', true)
                                    ->orderBy('nombre')
                                    ->pluck('nombre', 'id')
                            )
                            ->searchable()
                            ->placeholder('Ninguna'),
                    ]),

                Section::make('5. Incidencias Detectadas')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->schema([
                        Forms\Components\Toggle::make('hay_fuga_gas')
                            ->label('Hay Fuga de Gas')
                            ->helperText('Detecta fugas o escapes de gas')
                            ->default(false),
                        Forms\Components\Toggle::make('excede_capacidad')
                            ->label('Excede Capacidad')
                            ->helperText('La instalación supera capacidad permitida')
                            ->default(false),
                    ]),

                Section::make('6. Observaciones')
                    ->icon('heroicon-o-pencil-square')
                    ->schema([
                        Forms\Components\Textarea::make('observaciones')
                            ->label('Comentarios Adicionales')
                            ->placeholder('Agrega aquí cualquier observación importante sobre la inspección...')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),

                Section::make('7. Fotos Evidencias')
                    ->icon('heroicon-o-camera')
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

        $reporte = reportes::create([
            ...$data,
            'orden_job_id' => $this->orden->id,
            'numero_orden' => $this->orden->numero_orden,
            'ubicacions_id' => $ubicacionRecord->id,
            'comercios_id' => $comercioRecord->id,
        ]);

        $this->orden->update(['estado' => EstadoOrden::EnRevision]);

        $this->notificarCoordinador();

        if ($data['hay_fuga_gas'] || $data['excede_capacidad']) {
            $this->notificarIncidencias($reporte, $data);
        }

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

    /** @param array<string, mixed> $data */
    protected function notificarIncidencias(reportes $reporte, array $data): void
    {
        $correos = CorreoAlerta::where('activo', true)->pluck('email');

        if ($correos->isEmpty()) {
            return;
        }

        $incidencias = [];

        if ($data['hay_fuga_gas']) {
            $incidencias[] = 'Hay Fuga de Gas';
        }

        if ($data['excede_capacidad']) {
            $incidencias[] = 'Excede Capacidad';
        }

        foreach ($correos as $email) {
            try {
                Mail::to($email)->send(new IncidenciaDetectada($this->orden, $incidencias));
            } catch (\Exception $e) {
                Log::error('No se pudo enviar alerta de incidencia.', [
                    'orden' => $this->orden->numero_orden,
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function render(): View
    {
        return view('livewire.tecnico.reporte-create');
    }
}
