<?php

namespace App\Livewire\Tecnico;

use App\Enums\EstadoOrden;
use App\Enums\TipoServicio;
use App\Mail\IncidenciaDetectada;
use App\Mail\ReporteEnRevision;
use App\Models\comercio;
use App\Models\Convencion;
use App\Models\CorreoAlerta;
use App\Models\MarcaMedidor;
use App\Models\MarcaRegulador;
use App\Models\OrdenJob;
use App\Models\reportes;
use App\Models\ResultadoReporte;
use App\Models\TipoAnomalia;
use App\Models\TipoComercio;
use App\Models\TipoImposibilidad;
use App\Models\TipologiaOrden;
use App\Models\TipoNotificacionAlerta;
use App\Models\TipoRegulador;
use App\Models\ubicacion;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Flysystem\UnableToCheckExistence;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Formulario de creación del reporte de campo por parte del técnico.
 * El esquema del formulario varía según el tipo de servicio de la orden.
 */
#[Layout('layouts.app')]
#[Title('Crear Reporte')]
class ReporteCreate extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    public OrdenJob $orden;

    /** @var string|null Latitud capturada en el dispositivo del técnico; nullable si el navegador deniega la geolocalización. */
    public ?string $latitud = null;

    /** @var string|null Longitud capturada en el dispositivo del técnico; nullable si el navegador deniega la geolocalización. */
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

        $actividadesPredefinidas = collect($orden->actividades ?? [])
            ->map(fn ($a) => [
                'descripcion_orden' => $a['descripcion'] ?? '',
                'actividades' => '',
            ])
            ->toArray();

        $this->form->fill(
            $actividadesPredefinidas ? ['actividades' => $actividadesPredefinidas] : []
        );
    }

    public function form(Schema $form): Schema
    {
        $schema = match ($this->orden->tipo_servicio) {
            TipoServicio::Fraudes => $this->fraudesSchema(),
            TipoServicio::Investigacion => $this->investigacionSchema(),
            TipoServicio::Capturas, TipoServicio::Bandas => $this->capturasSchema(),
            TipoServicio::Vigilancia => $this->vigilanciaSchema(),
            TipoServicio::Gestion_Riesgo => $this->gestionRiesgoSchema(),
        };

        return $form->schema($schema)->statePath('data');
    }

    /** @return array<int, Section> */
    private function fraudesSchema(): array
    {
        return [
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
                        ->live()
                        ->default(false),
                    Forms\Components\FileUpload::make('foto_detector_fuga')
                        ->label('Foto del detector de fuga')
                        ->helperText('Toma o sube una foto del detector cuando se detecte fuga de gas')
                        ->disk('s3')
                        ->directory('reportes/'.$this->orden->numero_orden)
                        ->fetchFileInformation(false)
                        ->image()
                        ->imagePreviewHeight('100')
                        ->maxFiles(1)
                        ->maxSize(5120)
                        ->panelLayout('grid')
                        ->extraInputAttributes(['capture' => 'environment'])
                        ->visible(fn ($get) => (bool) $get('hay_fuga_gas'))
                        ->required(fn ($get) => (bool) $get('hay_fuga_gas'))
                        ->dehydrated(fn ($get) => (bool) $get('hay_fuga_gas'))
                        ->columnSpanFull(),
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
                        ->fetchFileInformation(false)
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
        ];
    }

    /** @return array<int, Section> */
    private function investigacionSchema(): array
    {
        return [
            Section::make('1. Datos del Reporte')
                ->icon('heroicon-o-document-magnifying-glass')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('convencion_id')
                        ->label('Convención')
                        ->options(
                            Convencion::query()
                                ->where('activo', true)
                                ->orderBy('nombre')
                                ->pluck('nombre', 'id')
                        )
                        ->searchable()
                        ->required()
                        ->placeholder('Seleccionar'),
                    Forms\Components\Toggle::make('acompanamiento_tecnico')
                        ->label('¿Hubo acompañamiento técnico?')
                        ->default(false)
                        ->live(),
                ]),

            Section::make('1b. Datos del Acompañante Técnico')
                ->icon('heroicon-o-user-group')
                ->columns(2)
                ->visible(fn ($get) => (bool) $get('acompanamiento_tecnico'))
                ->schema([
                    Forms\Components\TextInput::make('acompanante_nombre')
                        ->label('Nombre completo')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('acompanante_identificacion')
                        ->label('Identificación')
                        ->required()
                        ->maxLength(50),
                    Forms\Components\TextInput::make('acompanante_cargo')
                        ->label('Cargo / Empresa')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('acompanante_cuadrilla')
                        ->label('Código de Cuadrilla')
                        ->nullable()
                        ->maxLength(100),
                ]),

            Section::make('2. Persona o Comercio Investigado')
                ->icon('heroicon-o-user-circle')
                ->schema([
                    Forms\Components\Select::make('tipo_investigado')
                        ->label('Tipo de Investigado')
                        ->options(['persona' => 'Persona', 'comercio' => 'Comercio / Establecimiento'])
                        ->required()
                        ->live()
                        ->placeholder('Seleccionar'),
                    Forms\Components\TextInput::make('nombre_investigado')
                        ->label('Nombre completo')
                        ->maxLength(255)
                        ->visible(fn ($get) => $get('tipo_investigado') === 'persona'),
                    Forms\Components\TextInput::make('identificacion')
                        ->label('Identificación (CC / Pasaporte)')
                        ->maxLength(50)
                        ->visible(fn ($get) => $get('tipo_investigado') === 'persona'),
                    Forms\Components\Textarea::make('descripcion_fisica')
                        ->label('Descripción Física')
                        ->rows(3)
                        ->visible(fn ($get) => $get('tipo_investigado') === 'persona'),
                    Forms\Components\TextInput::make('nombre_comercio_inv')
                        ->label('Nombre del Comercio / Establecimiento')
                        ->maxLength(255)
                        ->visible(fn ($get) => $get('tipo_investigado') === 'comercio'),
                    Forms\Components\Select::make('tipo_comercio_inv_id')
                        ->label('Tipo de Comercio')
                        ->options(
                            TipoComercio::query()
                                ->where('activo', true)
                                ->orderBy('nombre')
                                ->pluck('nombre', 'id')
                        )
                        ->searchable()
                        ->placeholder('Seleccionar')
                        ->visible(fn ($get) => $get('tipo_investigado') === 'comercio'),
                ]),
            Section::make('3. Actividad Realizada')
                ->icon('heroicon-o-check-badge')
                ->schema([
                    Repeater::make('actividades')
                        ->label('Actividades Realizadas')
                        ->schema([
                            Forms\Components\TextInput::make('descripcion_orden')
                                ->label('Actividad asignada')
                                ->disabled()
                                ->dehydrated(true)
                                ->columnSpanFull()
                                ->placeholder('—'),
                            Forms\Components\Textarea::make('actividades')
                                ->autosize()
                                ->label('Respuesta del investigador')
                                ->columnSpanFull()
                                ->placeholder('Describe las actividades realizadas durante la investigación...')
                                ->required(),
                        ])
                        ->columns(1)
                        ->collapsible()
                        ->addable(false),
                ]),
            Section::make('4. Resultado')
                ->icon('heroicon-o-check-badge')
                ->schema([
                    Forms\Components\Select::make('resultado_reporte_id')
                        ->label('Resultado de la Investigación')
                        ->options(
                            ResultadoReporte::query()
                                ->where('activo', true)
                                ->orderBy('nombre')
                                ->pluck('nombre', 'id')
                        )
                        ->searchable()
                        ->placeholder('Seleccionar'),
                ]),

            Section::make('5. Observaciones')
                ->icon('heroicon-o-pencil-square')
                ->schema([
                    Forms\Components\Textarea::make('observaciones')
                        ->label('Novedades / Observaciones')
                        ->placeholder('Describe los hallazgos y novedades de la investigación...')
                        ->rows(4)
                        ->columnSpanFull(),
                ]),

            Section::make('6. Evidencias')
                ->icon('heroicon-o-camera')
                ->schema([
                    Forms\Components\FileUpload::make('imagenes')
                        ->label('Imágenes')
                        ->disk('s3')
                        ->directory('reportes/'.$this->orden->numero_orden)
                        ->fetchFileInformation(false)
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
        ];
    }

    /** @return array<int, Section> */
    private function capturasSchema(): array
    {
        return [
            Section::make('1. Datos de la Actividad')
                ->icon('heroicon-o-clipboard-document-list')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('tipologia_orden_id')
                        ->label('Tipología de Orden')
                        ->options(
                            TipologiaOrden::query()
                                ->where('activo', true)
                                ->orderBy('nombre')
                                ->pluck('nombre', 'id')
                        )
                        ->searchable()
                        ->required()
                        ->placeholder('Seleccionar'),
                    Forms\Components\DatePicker::make('fecha_ejecucion')
                        ->label('Fecha de Ejecución')
                        ->required()
                        ->displayFormat('d/m/Y'),
                    Forms\Components\TextInput::make('departamento')
                        ->label('Departamento')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('municipio')
                        ->label('Municipio')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('radio_objetivo')
                        ->label('Radio del Objetivo')
                        ->maxLength(255)
                        ->columnSpanFull(),
                ]),
            Section::make('3. Actividad Realizada')
                ->icon('heroicon-o-check-badge')
                ->schema([
                    Repeater::make('actividades')
                        ->label('Actividades Por Realizar')
                        ->schema([
                            Forms\Components\TextInput::make('descripcion_orden')
                                ->label('Actividad asignada')
                                ->disabled()
                                ->dehydrated(true)
                                ->columnSpanFull()
                                ->placeholder('—'),
                            Forms\Components\Textarea::make('actividades')
                                ->autosize()
                                ->label('Respuesta del Investigador')
                                ->columnSpanFull()
                                ->placeholder('Describe las actividades realizadas...')
                                ->required(),
                        ])
                        ->columns(1)
                        ->collapsible()
                        ->addable(false),
                ]),
            Section::make('2. Observaciones')
                ->icon('heroicon-o-pencil-square')
                ->schema([
                    Forms\Components\Textarea::make('observaciones')
                        ->label('Novedades / Observaciones')
                        ->placeholder('Describe el desarrollo de la actividad...')
                        ->rows(4)
                        ->columnSpanFull(),
                ]),
            Section::make('4. Evidencias')
                ->icon('heroicon-o-camera')
                ->schema([
                    Forms\Components\FileUpload::make('imagenes')
                        ->label('Imágenes')
                        ->disk('s3')
                        ->directory('reportes/'.$this->orden->numero_orden)
                        ->fetchFileInformation(false)
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
        ];
    }

    /** @return array<int, Section> */
    private function gestionRiesgoSchema(): array
    {
        return [
            Section::make('1. Actividad Realizada')
                ->icon('heroicon-o-check-badge')
                ->schema([
                    Repeater::make('actividades')
                        ->label('Actividades Realizadas')
                        ->schema([
                            Forms\Components\TextInput::make('descripcion_orden')
                                ->label('Actividad asignada')
                                ->disabled()
                                ->dehydrated(true)
                                ->columnSpanFull()
                                ->placeholder('—'),
                            Forms\Components\Textarea::make('actividades')
                                ->autosize()
                                ->label('Respuesta del técnico')
                                ->columnSpanFull()
                                ->placeholder('Describe las actividades realizadas...')
                                ->required(),
                        ])
                        ->columns(1)
                        ->collapsible()
                        ->addable(false),
                ]),

            Section::make('2. Observaciones')
                ->icon('heroicon-o-pencil-square')
                ->schema([
                    Forms\Components\Textarea::make('observaciones')
                        ->label('Novedades / Observaciones')
                        ->placeholder('Describe los hallazgos y novedades de la gestión de riesgo...')
                        ->rows(4)
                        ->columnSpanFull(),
                ]),

            Section::make('3. Evidencias')
                ->icon('heroicon-o-paper-clip')
                ->schema([
                    Forms\Components\FileUpload::make('imagenes')
                        ->label('Archivos / Evidencias')
                        ->helperText('Acepta imágenes, PDF, Word, Excel y PowerPoint (máx. 10 MB por archivo)')
                        ->disk('s3')
                        ->directory('reportes/'.$this->orden->numero_orden)
                        ->fetchFileInformation(false)
                        ->multiple()
                        ->acceptedFileTypes([
                            'image/*',
                            'application/pdf',
                            'application/msword',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-powerpoint',
                            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                        ])
                        ->maxFiles(10)
                        ->maxSize(10240)
                        ->reorderable()
                        ->panelLayout('grid')
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('video')
                        ->label('URL del Video')
                        ->url()
                        ->maxLength(500),
                ]),
        ];
    }

    /** @return array<int, Section> */
    private function vigilanciaSchema(): array
    {
        return [
            Section::make('1. Turno')
                ->icon('heroicon-o-clock')
                ->columns(3)
                ->schema([
                    Forms\Components\DatePicker::make('fecha_turno')
                        ->label('Fecha del Turno')
                        ->required()
                        ->displayFormat('d/m/Y'),
                    Forms\Components\TimePicker::make('hora_inicio')
                        ->label('Hora de Inicio')
                        ->required(),
                    Forms\Components\TimePicker::make('hora_fin')
                        ->label('Hora de Fin')
                        ->required(),
                ]),

            Section::make('2. Novedades')
                ->icon('heroicon-o-bell-alert')
                ->schema([
                    Forms\Components\Toggle::make('hay_novedad')
                        ->label('¿Hay novedades?')
                        ->helperText('Activa si hubo novedades durante el turno')
                        ->default(false)
                        ->live(),
                    Forms\Components\Textarea::make('descripcion_novedad')
                        ->label('Descripción de las Novedades')
                        ->placeholder('Detalla las novedades observadas durante el turno...')
                        ->rows(4)
                        ->required(fn ($get) => (bool) $get('hay_novedad'))
                        ->visible(fn ($get) => (bool) $get('hay_novedad'))
                        ->columnSpanFull(),
                    Forms\Components\Toggle::make('hay_reincidencia')
                        ->label('¿Hay reincidencia detectada?')
                        ->default(false)
                        ->visible(fn ($get) => (bool) $get('hay_novedad')),
                ]),

            Section::make('3. Resultado')
                ->icon('heroicon-o-check-badge')
                ->schema([
                    Forms\Components\Select::make('resultado_reporte_id')
                        ->label('Resultado del Turno')
                        ->options(
                            ResultadoReporte::query()
                                ->where('activo', true)
                                ->orderBy('nombre')
                                ->pluck('nombre', 'id')
                        )
                        ->searchable()
                        ->placeholder('Seleccionar'),
                ]),

            Section::make('4. Observaciones')
                ->icon('heroicon-o-pencil-square')
                ->schema([
                    Forms\Components\Textarea::make('observaciones')
                        ->label('Observaciones Generales')
                        ->placeholder('Observaciones generales del turno...')
                        ->rows(4)
                        ->columnSpanFull(),
                ]),

            Section::make('5. Evidencias')
                ->icon('heroicon-o-camera')
                ->schema([
                    Forms\Components\FileUpload::make('imagenes')
                        ->label('Imágenes')
                        ->disk('s3')
                        ->directory('reportes/'.$this->orden->numero_orden)
                        ->fetchFileInformation(false)
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
        ];
    }

    /**
     * Despacha al método de guardado específico según el tipo de servicio.
     * Fraudes tiene lógica propia por el comercio y la ubicación; Vigilancia no cierra la orden.
     */
    public function submit(): void
    {
        $data = $this->form->getState();

        // Normalizar actividades con UUID
        if (! empty($data['actividades']) && is_array($data['actividades'])) {
            foreach ($data['actividades'] as $i => $item) {
                if (empty($item['uuid'])) {
                    $data['actividades'][$i]['uuid'] = (string) Str::uuid();
                }
            }
        }

        match ($this->orden->tipo_servicio) {
            TipoServicio::Fraudes => $this->submitFraudes($data),
            TipoServicio::Investigacion => $this->submitServicioGenerico($data),
            TipoServicio::Capturas => $this->submitServicioGenerico($data),
            TipoServicio::Bandas => $this->submitServicioGenerico($data),
            TipoServicio::Vigilancia => $this->submitVigilancia($data),
            TipoServicio::Gestion_Riesgo => $this->submitServicioGenerico($data),
        };
    }

    /** @param array<string, mixed> $data */
    private function submitFraudes(array $data): void
    {
        $comercioRecord = comercio::create([
            'nombre_comercio' => $data['nombre_comercio'],
            'tipo_comercio_id' => $data['tipo_comercio_id'],
        ]);

        $imagenes = $this->normalizarImagenes($data);
        $fotoDetectorFuga = $this->extraerFotoDetectorFuga($data);
        $fotoDetectorData = $this->cargarFotoDetectorData($fotoDetectorFuga);
        $fotoDetectorMimeType = $this->cargarFotoDetectorMimeType($fotoDetectorFuga);

        unset($data['nombre_comercio'], $data['tipo_comercio_id'], $data['foto_detector_fuga']);

        $ubicacionRecord = ubicacion::create([
            'latitud' => $this->latitud,
            'longitud' => $this->longitud,
            'direccion' => $this->orden->direccion,
        ]);

        $reporte = reportes::create([
            ...$data,
            'imagenes' => $imagenes,
            'orden_job_id' => $this->orden->id,
            'personals_id' => auth()->user()->personals_id,
            'numero_orden' => $this->orden->numero_orden,
            'ubicacions_id' => $ubicacionRecord->id,
            'comercios_id' => $comercioRecord->id,
            'actividades' => $data['actividades'] ?? null,
        ]);

        $this->orden->update(['estado' => EstadoOrden::EnRevision]);

        $this->notificarCoordinador();

        $bypassId = TipoAnomalia::where('activo', true)->where('nombre', 'Bypass')->value('id');
        $hasBypass = $bypassId
            && ! empty($data['anomalia'])
            && is_array($data['anomalia'])
            && in_array($bypassId, $data['anomalia'], true);

        if ($data['hay_fuga_gas'] || $data['excede_capacidad'] || $hasBypass) {
            $this->notificarIncidencias($reporte, $data, $fotoDetectorFuga, $fotoDetectorData, $fotoDetectorMimeType);
        }

        $this->redirect(route('tecnico.mis-ordenes'), navigate: true);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, string>|null
     */
    private function normalizarImagenes(array $data): ?array
    {
        $imagenes = [];

        if (! empty($data['imagenes']) && is_array($data['imagenes'])) {
            $imagenes = array_values($data['imagenes']);
        }

        if (! empty($data['foto_detector_fuga'])) {
            $fotoDetectorFuga = $data['foto_detector_fuga'];

            if (is_array($fotoDetectorFuga)) {
                $imagenes = array_merge($imagenes, array_values($fotoDetectorFuga));
            } elseif (is_string($fotoDetectorFuga)) {
                $imagenes[] = $fotoDetectorFuga;
            }
        }

        return $imagenes !== [] ? $imagenes : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function extraerFotoDetectorFuga(array $data): ?string
    {
        if (empty($data['foto_detector_fuga'])) {
            return null;
        }

        $fotoDetectorFuga = $data['foto_detector_fuga'];

        if (is_array($fotoDetectorFuga)) {
            $fotoDetectorFuga = reset($fotoDetectorFuga);
        }

        return is_string($fotoDetectorFuga) && $fotoDetectorFuga !== '' ? $fotoDetectorFuga : null;
    }

    private function cargarFotoDetectorData(?string $fotoDetectorFuga): ?string
    {
        if (! $fotoDetectorFuga) {
            return null;
        }

        try {
            return Storage::disk('s3')->exists($fotoDetectorFuga)
                ? Storage::disk('s3')->get($fotoDetectorFuga)
                : null;
        } catch (UnableToCheckExistence) {
            return null;
        }
    }

    private function cargarFotoDetectorMimeType(?string $fotoDetectorFuga): ?string
    {
        if (! $fotoDetectorFuga) {
            return null;
        }

        try {
            return Storage::disk('s3')->exists($fotoDetectorFuga)
                ? Storage::disk('s3')->mimeType($fotoDetectorFuga)
                : null;
        } catch (UnableToCheckExistence) {
            return null;
        }
    }

    /** @param array<string, mixed> $data */
    private function submitServicioGenerico(array $data): void
    {
        $datosServicio = $data;
        unset($datosServicio['observaciones'], $datosServicio['imagenes'], $datosServicio['video'], $datosServicio['actividades']);

        reportes::create([
            'orden_job_id' => $this->orden->id,
            'personals_id' => auth()->user()->personals_id,
            'numero_orden' => $this->orden->numero_orden,
            'observaciones' => $data['observaciones'] ?? null,
            'imagenes' => $data['imagenes'] ?? null,
            'video' => $data['video'] ?? null,
            'datos_servicio' => $datosServicio,
            'actividades' => $data['actividades'] ?? null,
        ]);

        $this->orden->update(['estado' => EstadoOrden::EnRevision]);

        $this->notificarCoordinador();

        $this->redirect(route('tecnico.mis-ordenes'), navigate: true);
    }

    /** @param array<string, mixed> $data */
    private function submitVigilancia(array $data): void
    {
        $datosServicio = $data;
        unset($datosServicio['observaciones'], $datosServicio['imagenes'], $datosServicio['video'], $datosServicio['actividades']);

        reportes::create([
            'orden_job_id' => $this->orden->id,
            'personals_id' => auth()->user()->personals_id,
            'numero_orden' => $this->orden->numero_orden,
            'observaciones' => $data['observaciones'] ?? null,
            'imagenes' => $data['imagenes'] ?? null,
            'video' => $data['video'] ?? null,
            'datos_servicio' => $datosServicio,
            'actividades' => $data['actividades'] ?? null,
        ]);

        // Vigilancia permanece EnProgreso hasta que el investigador la cierre manualmente
        $this->redirect(route('tecnico.mis-ordenes'), navigate: true);
    }

    /** Avisa al coordinador que el técnico envió el reporte para revisión. */
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

    /**
     * Envía alertas a todos los correos activos cuando se detecta fuga de gas o exceso de capacidad.
     * Solo aplica a órdenes de tipo Fraudes.
     *
     * @param  array<string, mixed>  $data
     */
    protected function notificarIncidencias(
        reportes $reporte,
        array $data,
        ?string $fotoDetectorFuga = null,
        ?string $fotoDetectorData = null,
        ?string $fotoDetectorMimeType = null,
    ): void {
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

        if (! empty($data['anomalia']) && is_array($data['anomalia'])) {
            $bypassId = TipoAnomalia::where('activo', true)->where('nombre', 'Bypass')->value('id');

            if ($bypassId && in_array($bypassId, $data['anomalia'], true)) {
                $incidencias[] = 'Bypass';
            }
        }

        foreach ($correos as $email) {
            try {
                Mail::to($email)->send(new IncidenciaDetectada(
                    $this->orden,
                    $incidencias,
                    $fotoDetectorFuga,
                    $fotoDetectorData,
                    $fotoDetectorMimeType,
                    $fotoDetectorFuga ? basename($fotoDetectorFuga) : null,
                ));
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
