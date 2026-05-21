<?php

namespace App\Livewire\Tecnico;

use App\Enums\EstadoOrden;
use App\Enums\TipoServicio;
use App\Filament\Forms\Components\SignaturePad;
use App\Mail\IncidenciaDetectada;
use App\Mail\ReporteEnRevision;
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

#[Layout('layouts.app')]
#[Title('Editar Reporte')]
/**
 * Formulario de corrección del reporte rechazado por el coordinador.
 * Solo accesible cuando la orden está en estado Rechazada; permite al técnico subsanar las observaciones.
 */
class ReporteEdit extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    public OrdenJob $orden;

    public reportes $reporte;

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

        abort_unless($orden->estado === EstadoOrden::Rechazada, 403);

        $this->orden = $orden;
        $this->reporte = $orden->reportes()->latest()->firstOrFail();

        if ($this->orden->tipo_servicio === TipoServicio::Fraudes || $this->orden->tipo_servicio === TipoServicio::RevisionMedidores) {
            $data = [
                ...$this->reporte->toArray(),
                'nombre_comercio' => $this->reporte->comercio?->nombre_comercio,
                'tipo_comercio_id' => $this->reporte->comercio?->tipo_comercio_id,
            ];

            if (! empty($data['imagenes'])) {
                $data['imagenes'] = $this->prepareFileUploadFromPaths($data['imagenes']);
            }

            $this->form->fill($data);
        } else {
            $data = [
                ...($this->reporte->datos_servicio ?? []),
                'observaciones' => $this->reporte->observaciones,
                'imagenes' => $this->reporte->imagenes,
                'video' => $this->reporte->video,
                'actividades' => $this->reporte->actividades,
            ];

            if (! empty($data['imagenes'])) {
                $data['imagenes'] = $this->prepareFileUploadFromPaths($data['imagenes']);
            }

            $this->form->fill($data);
        }
    }

    /**
     * Convierte un array de rutas (strings) en la estructura que FileUpload usa para previews.
     * Solo genera URLs/metadata fuera de los tests para evitar llamadas a Storage en las pruebas.
     *
     * @param  array<int,string>|null  $paths
     * @return array<int,array<string,mixed>>|null
     */
    private function prepareFileUploadFromPaths(?array $paths): ?array
    {
        if (empty($paths) || ! is_array($paths)) {
            return null;
        }

        // Durante tests dejamos las rutas tal cual para evitar Storage::disk calls.
        if (app()->runningUnitTests()) {
            return $paths;
        }

        $result = [];
        foreach ($paths as $p) {
            try {
                if (! Storage::disk('s3')->exists($p)) {
                    continue;
                }

                $name = basename($p);
                $size = null;
                $mime = null;

                try {
                    $size = Storage::disk('s3')->size($p);
                } catch (\Throwable $e) {
                    // ignore
                }

                try {
                    $mime = Storage::disk('s3')->mimeType($p);
                } catch (\Throwable $e) {
                    // ignore
                }

                // URL temporal (5 minutos)
                $url = null;
                try {
                    $url = Storage::disk('s3')->temporaryUrl($p, now()->addMinutes(5));
                } catch (\Throwable $e) {
                    $url = Storage::disk('s3')->url($p);
                }

                $result[] = [
                    'name' => $name,
                    'size' => $size,
                    'type' => $mime,
                    'url' => $url,
                    'path' => $p,
                ];
            } catch (\Throwable $e) {
                // no interrumpimos si un archivo falla
                continue;
            }
        }

        return $result !== [] ? $result : null;
    }

    public function form(Schema $form): Schema
    {
        $schema = match ($this->orden->tipo_servicio) {
            TipoServicio::Fraudes,
            TipoServicio::RevisionMedidores => $this->fraudesSchema(),
            TipoServicio::Investigacion => $this->investigacionSchema(),
            TipoServicio::GestionLegal => $this->gestionLegalSchema(),
            TipoServicio::Gestion_Riesgo => $this->gestionRiesgoSchema(),
            TipoServicio::Vigilancia => $this->vigilanciaSchema(),
            TipoServicio::AuditoriaCampo => $this->auditoriaCampoSchema(),
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
                        ->fetchFileInformation(fn (): bool => ! app()->runningUnitTests())
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
                        ->fetchFileInformation(fn (): bool => ! app()->runningUnitTests())
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
                            Forms\Components\Textarea::make('descripcion_orden')
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
                        ->fetchFileInformation(fn (): bool => ! app()->runningUnitTests())
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
    private function gestionLegalSchema(): array
    {
        return [
            Section::make('1. Datos de la Actividad')
                ->icon('heroicon-o-clipboard-document-list')
                ->columns(2)
                ->schema([
                    Forms\Components\Placeholder::make('tipo_gestion_legal_display')
                        ->label('Tipo de Gestión Legal')
                        ->content(function (): string {
                            $subtipos = [
                                'capturas' => 'Capturas Criminales',
                                'bandas' => 'Desarticulación de Bandas',
                                'querella' => 'Querella Penal',
                                'seguimiento_denuncia' => 'Seguimiento a Denuncia',
                                'diligencia_judicial' => 'Diligencia Judicial',
                            ];

                            return $subtipos[$this->orden->tipo_gestion_legal ?? ''] ?? ($this->orden->tipo_gestion_legal ?? '—');
                        }),
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

            Section::make('2. Actividad Realizada')
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

            Section::make('3. Observaciones')
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
                        ->fetchFileInformation(fn (): bool => ! app()->runningUnitTests())
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
                        ->placeholder('Describe los hallazgos y novedades...')
                        ->rows(4)
                        ->columnSpanFull(),
                ]),

            Section::make('3. Evidencias')
                ->icon('heroicon-o-paper-clip')
                ->schema([
                    Forms\Components\FileUpload::make('imagenes')
                        ->label('Archivos / Evidencias')
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

    /** @return array<int, Section> */
    private function auditoriaCampoSchema(): array
    {
        $subtipo = match ($this->orden->tipo_auditoria_campo) {
            'visita_calidad_emergencia' => 'Visita de Calidad — Emergencia',
            'visita_calidad_trabajos_varios' => 'Visita de Calidad — Trabajos Varios',
            default => 'Auditoría de Campo',
        };

        return [
            Section::make('1. Información General')
                ->icon('heroicon-o-document-text')
                ->columns(2)
                ->schema([
                    Forms\Components\Placeholder::make('subtipo_display')
                        ->label('Subtipo')
                        ->content($subtipo)
                        ->columnSpanFull(),
                    Forms\Components\Placeholder::make('ot_display')
                        ->label('OT')
                        ->content($this->orden->numero_orden),
                    Forms\Components\Placeholder::make('contrato_display')
                        ->label('Contrato')
                        ->content($this->orden->contrato ?? '—'),
                    Forms\Components\Placeholder::make('cliente_display')
                        ->label('Nombre del Usuario')
                        ->content($this->orden->cliente ?? '—'),
                    Forms\Components\Placeholder::make('direccion_display')
                        ->label('Dirección')
                        ->content($this->orden->direccion ?? '—'),
                    Forms\Components\DateTimePicker::make('fecha_hora_atencion')
                        ->label('Fecha y Hora de Atención')
                        ->required()
                        ->displayFormat('d/m/Y H:i')
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('ciudad')
                        ->label('Ciudad')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('celular_usuario')
                        ->label('Celular del Usuario')
                        ->tel()
                        ->maxLength(20),
                    Forms\Components\Textarea::make('comentario_usuario')
                        ->label('Comentario Usuario / Solicitud')
                        ->rows(3)
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('comentario_tecnico')
                        ->label('Comentario del Técnico')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),

            Section::make('2. Datos de Quién Atiende la Visita')
                ->icon('heroicon-o-user-circle')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('nombre_atiende')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\Select::make('vinculo_usuario')
                        ->label('Vínculo con el usuario')
                        ->options([
                            'titular' => 'Titular',
                            'familiar' => 'Familiar',
                            'vecino' => 'Vecino',
                            'otro' => 'Otro',
                        ])
                        ->placeholder('Seleccionar'),
                    Forms\Components\TextInput::make('celular_atiende')
                        ->label('Celular')
                        ->tel()
                        ->maxLength(20),
                ]),

            Section::make('3. Validación de la Atención y Ejecución')
                ->icon('heroicon-o-check-circle')
                ->columns(2)
                ->schema([
                    Forms\Components\Toggle::make('recibio_visita')
                        ->label('¿Recibió la visita del técnico?')
                        ->required()
                        ->default(false),
                    Forms\Components\Toggle::make('se_realizo_revision')
                        ->label('¿Se realizó revisión técnica o trabajos?')
                        ->required()
                        ->default(false),
                    Forms\Components\DatePicker::make('fecha_ejecucion')
                        ->label('Fecha de ejecución')
                        ->displayFormat('d/m/Y'),
                    Forms\Components\TextInput::make('tiempo_ejecucion')
                        ->label('Tiempo aproximado de ejecución')
                        ->placeholder('Ej: 2 horas')
                        ->maxLength(100),
                    Forms\Components\Textarea::make('descripcion_trabajo')
                        ->label('Descripción del trabajo / reparación realizada')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),

            Section::make('4. Hallazgos Técnicos y Validación Física')
                ->icon('heroicon-o-magnifying-glass')
                ->columns(2)
                ->schema([
                    Forms\Components\Toggle::make('se_detecto_fuga')
                        ->label('¿Se detectó fuga o falla?')
                        ->default(false)
                        ->live()
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('ppm')
                        ->label('Medición PPM')
                        ->maxLength(50)
                        ->visible(fn ($get) => (bool) $get('se_detecto_fuga')),
                    Forms\Components\TextInput::make('vol_porcentaje')
                        ->label('VOL %')
                        ->maxLength(50)
                        ->visible(fn ($get) => (bool) $get('se_detecto_fuga')),
                    Forms\Components\Toggle::make('problema_persiste')
                        ->label('¿Actualmente el problema persiste?')
                        ->default(false),
                    Forms\Components\Toggle::make('evidencia_reparacion')
                        ->label('¿Se evidencia la reparación en el punto?')
                        ->default(false),
                    Forms\Components\Toggle::make('servicio_correcto')
                        ->label('¿El servicio funciona correctamente?')
                        ->default(false)
                        ->columnSpanFull(),
                ]),

            Section::make('5. Materiales y Cobros')
                ->icon('heroicon-o-currency-dollar')
                ->columns(2)
                ->schema([
                    Forms\Components\Toggle::make('se_usaron_materiales')
                        ->label('¿Se utilizaron materiales?')
                        ->default(false)
                        ->live()
                        ->columnSpanFull(),
                    Forms\Components\Select::make('quien_suministro_materiales')
                        ->label('¿Quién suministró los materiales?')
                        ->options([
                            'tecnico' => 'Técnico',
                            'usuario' => 'Usuario',
                            'empresa' => 'Empresa',
                        ])
                        ->placeholder('Seleccionar')
                        ->visible(fn ($get) => (bool) $get('se_usaron_materiales'))
                        ->columnSpanFull(),
                    Forms\Components\Toggle::make('usuario_asumio_costo')
                        ->label('¿El usuario asumió algún costo?')
                        ->default(false)
                        ->live()
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('valor_pagado')
                        ->label('Valor pagado')
                        ->maxLength(100)
                        ->visible(fn ($get) => (bool) $get('usuario_asumio_costo')),
                    Forms\Components\Select::make('medio_pago')
                        ->label('Medio de pago')
                        ->options([
                            'efectivo' => 'Efectivo',
                            'transferencia' => 'Transferencia',
                        ])
                        ->placeholder('Seleccionar')
                        ->visible(fn ($get) => (bool) $get('usuario_asumio_costo')),
                    Forms\Components\Toggle::make('entrego_factura')
                        ->label('¿Se entregó factura, IVT o soporte de cobro?')
                        ->default(false)
                        ->columnSpanFull(),
                ]),

            Section::make('6. Trazabilidad y Contradicción')
                ->icon('heroicon-o-document-check')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('tecnico_dejo_documento')
                        ->label('¿El técnico dejó algún documento (IVT, certificado)?')
                        ->maxLength(255)
                        ->columnSpanFull(),
                    Forms\Components\Toggle::make('informaron_no_estaba')
                        ->label('¿Le informaron erróneamente que no estaba en la vivienda?')
                        ->default(false),
                ]),

            Section::make('7. Autorización y Firmas')
                ->icon('heroicon-o-pencil')
                ->columns(2)
                ->schema([
                    Forms\Components\Toggle::make('autoriza')
                        ->label('¿El usuario autoriza la recolección de esta información?')
                        ->required()
                        ->default(false)
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('cedula_usuario')
                        ->label('Cédula del Usuario')
                        ->required()
                        ->maxLength(20),
                    Forms\Components\TextInput::make('cedula_auditor')
                        ->label('Cédula del Auditor')
                        ->required()
                        ->maxLength(20),
                    SignaturePad::make('firma_usuario')
                        ->label('Firma del Usuario')
                        ->columnSpanFull(),
                    SignaturePad::make('firma_auditor')
                        ->label('Firma del Auditor')
                        ->columnSpanFull(),
                ]),

            Section::make('8. Observaciones')
                ->icon('heroicon-o-pencil-square')
                ->schema([
                    Forms\Components\Textarea::make('observaciones')
                        ->label('Observaciones')
                        ->placeholder('Observaciones adicionales...')
                        ->rows(4)
                        ->columnSpanFull(),
                ]),

            Section::make('9. Soporte Fotográfico y Evidencias')
                ->icon('heroicon-o-camera')
                ->schema([
                    Forms\Components\FileUpload::make('imagenes')
                        ->label('Fotos')
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
                    Forms\Components\TagsInput::make('urls_evidencia')
                        ->label('URLs de evidencia / documentos')
                        ->placeholder('Pega una URL y presiona Enter')
                        ->hint('Agrega links a documentos, Google Drive, etc.')
                        ->columnSpanFull(),
                ]),
        ];
    }

    /**
     * Persiste las correcciones al reporte y lo vuelve a enviar a revisión del coordinador.
     * Fraudes actualiza también el comercio asociado; el resto usa datos_servicio.
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
            TipoServicio::Fraudes,
            TipoServicio::RevisionMedidores => $this->submitFraudes($data),
            default => $this->submitServicioGenerico($data),
        };
    }

    private function actualizarUbicacion(): void
    {
        if ($this->reporte->ubicacion) {
            $this->reporte->ubicacion->update([
                'latitud' => $this->latitud,
                'longitud' => $this->longitud,
            ]);
        } else {
            $ubicacionRecord = ubicacion::create([
                'latitud' => $this->latitud,
                'longitud' => $this->longitud,
                'direccion' => $this->orden->direccion,
            ]);
            $this->reporte->update(['ubicacions_id' => $ubicacionRecord->id]);
        }
    }

    /** @param array<string, mixed> $data */
    private function submitFraudes(array $data): void
    {
        $this->reporte->comercio()->update([
            'nombre_comercio' => $data['nombre_comercio'],
            'tipo_comercio_id' => $data['tipo_comercio_id'],
        ]);

        $imagenes = $this->normalizarImagenes($data);
        $fotoDetectorFuga = $this->extraerFotoDetectorFuga($data);
        $fotoDetectorData = $this->cargarFotoDetectorData($fotoDetectorFuga);
        $fotoDetectorMimeType = $this->cargarFotoDetectorMimeType($fotoDetectorFuga);

        unset($data['nombre_comercio'], $data['tipo_comercio_id'], $data['foto_detector_fuga']);

        $this->reporte->update([
            ...$data,
            'imagenes' => $imagenes,
        ]);

        $this->actualizarUbicacion();

        $this->orden->update(['estado' => EstadoOrden::EnRevision]);

        $this->notificarCoordinador();

        if (($data['hay_fuga_gas'] ?? false) || ($data['excede_capacidad'] ?? false)) {
            $this->notificarIncidencias($data, $fotoDetectorFuga, $fotoDetectorData, $fotoDetectorMimeType);
        }

        $this->dispatch('borrador-limpiar');

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

        $this->reporte->update([
            'observaciones' => $data['observaciones'] ?? null,
            'imagenes' => $data['imagenes'] ?? null,
            'video' => $data['video'] ?? null,
            'datos_servicio' => $datosServicio,
            'actividades' => $data['actividades'] ?? null,
        ]);

        $this->actualizarUbicacion();

        $this->orden->update(['estado' => EstadoOrden::EnRevision]);

        $this->notificarCoordinador();

        $this->dispatch('borrador-limpiar');

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
    protected function notificarIncidencias(
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

        if ($data['hay_fuga_gas'] ?? false) {
            $incidencias[] = 'Hay Fuga de Gas';
        }

        if ($data['excede_capacidad'] ?? false) {
            $incidencias[] = 'Excede Capacidad';
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
        return view('livewire.tecnico.reporte-edit');
    }
}
