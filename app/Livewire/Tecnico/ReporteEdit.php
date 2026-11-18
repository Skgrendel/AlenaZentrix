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
use App\Models\TipologiaOrden;
use App\Models\TipoNotificacionAlerta;
use App\Models\TipoRegulador;
use App\Models\ubicacion;
use App\Support\ArchivoUrl;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
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

    public ?reportes $reporte = null;

    /**
     * Turnos de Vigilancia marcados para corrección cuando hay más de uno pendiente.
     * Si tiene valor, la vista muestra un selector en vez del formulario.
     *
     * @var Collection<int, reportes>|null
     */
    public ?Collection $turnosPendientes = null;

    /** @var array<int, string> */
    public array $imagenesExistentes = [];

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

        $esVigilancia = $orden->tipo_servicio === TipoServicio::Vigilancia;
        $tienePendientes = $esVigilancia && $orden->reportes()->where('requiere_correccion', true)->exists();

        // Fuera de Vigilancia, cada orden tiene un solo reporte: rechazar bloquea toda la
        // orden. En Vigilancia, un turno puede quedar marcado para corrección sin que la
        // orden pase a Rechazada, así que también se permite entrar en ese caso.
        abort_unless($orden->estado === EstadoOrden::Rechazada || $tienePendientes, 403);

        $reporteId = request()->query('reporte');

        if ($esVigilancia) {
            $pendientes = $orden->reportes()->where('requiere_correccion', true)->latest('updated_at')->get();

            if ($reporteId) {
                $this->reporte = $pendientes->firstWhere('id', $reporteId);
                abort_if(! $this->reporte, 404);
            } elseif ($pendientes->count() === 1) {
                $this->reporte = $pendientes->first();
            } elseif ($pendientes->count() > 1) {
                // Varios turnos requieren corrección: se muestra un selector en vez del
                // formulario hasta que el técnico elija cuál editar.
                $this->turnosPendientes = $pendientes;

                return;
            } else {
                // Orden Rechazada sin ningún turno marcado (compatibilidad con datos
                // previos a que existiera requiere_correccion): se edita el más reciente.
                $this->reporte = $orden->reportes()->latest()->first();

                if (! $this->reporte) {
                    session()->flash('error', 'Esta orden de vigilancia no tiene turnos por corregir. Agrega un nuevo reporte desde Mis Órdenes.');
                    $this->redirectRoute('tecnico.mis-ordenes', navigate: true);

                    return;
                }
            }
        } else {
            // Prioriza el reporte específico marcado como rechazado. El fallback a latest()
            // cubre órdenes ya rechazadas antes de este fix (donde ningún reporte quedó marcado).
            $this->reporte = $orden->reportes()->where('requiere_correccion', true)->latest('updated_at')->first()
                ?? $orden->reportes()->latest()->firstOrFail();
        }

        // Descarta referencias temporales de Livewire que hayan quedado guardadas de una
        // corrección anterior a este fix, para que no se sigan arrastrando indefinidamente.
        $this->imagenesExistentes = collect($this->reporte->imagenes ?? [])
            ->reject(fn (string $path) => ArchivoUrl::esReferenciaTemporal($path))
            ->values()
            ->all();

        if ($this->orden->tipo_servicio === TipoServicio::Fraudes || $this->orden->tipo_servicio === TipoServicio::RevisionMedidores) {
            $this->form->fill([
                ...$this->reporte->toArray(),
                'imagenes' => [],
                'nombre_comercio' => $this->reporte->comercio?->nombre_comercio,
                'tipo_comercio_id' => $this->reporte->comercio?->tipo_comercio_id,
            ]);
        } else {
            $datosServicio = $this->reporte->datos_servicio ?? [];

            if ($this->orden->tipo_servicio === TipoServicio::AuditoriaCampo) {
                // comentario_usuario/comentario_tecnico son editables en este formulario, pero
                // el reporte solo guarda lo que el técnico haya escrito aquí. Si aún no se ha
                // corregido este campo, se precarga con el valor original de la orden en vez de
                // dejarlo en blanco (el "+" no sobrescribe si datos_servicio ya trae un valor).
                $datosServicio += [
                    'comentario_usuario' => $this->orden->comentario_usuario,
                    'comentario_tecnico' => $this->orden->comentario_tecnico,
                ];
            }

            $this->form->fill([
                ...$datosServicio,
                'observaciones' => $this->reporte->observaciones,
                'imagenes' => [],
                'video' => $this->reporte->video,
                'geo_referencia' => $this->reporte->geo_referencia,
                'actividades' => $this->reporte->actividades,
            ]);
        }
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
                    Forms\Components\ToggleButtons::make('coincide_medidor')
                        ->label('¿Coincide con el medidor asignado?')
                        ->siNo()
                        ->default(0),
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
                    Forms\Components\ToggleButtons::make('hay_fuga_gas')
                        ->label('Hay Fuga de Gas')
                        ->siNo()
                        ->helperText('Detecta fugas o escapes de gas')
                        ->live()
                        ->default(0),
                    Forms\Components\FileUpload::make('foto_detector_fuga')
                        ->label('Foto del detector de fuga')
                        ->helperText('Toma o sube una foto del detector cuando se detecte fuga de gas')
                        ->disk('s3')
                        ->directory('reportes/'.$this->orden->numero_orden)
                        ->fetchFileInformation(false)
                        ->previewable(false)
                        ->image()
                        ->imagePreviewHeight('100')
                        ->maxFiles(1)
                        ->maxSize(5120)
                        ->panelLayout('grid')
                        ->extraInputAttributes(['capture' => 'environment'])
                        ->visible(fn ($get) => (bool) $get('hay_fuga_gas'))
                        ->required(fn ($get) => (bool) $get('hay_fuga_gas') && ! $this->tieneFotoDetectorGuardada())
                        ->dehydrated(fn ($get) => (bool) $get('hay_fuga_gas'))
                        ->columnSpanFull(),
                    Forms\Components\ToggleButtons::make('excede_capacidad')
                        ->label('Excede Capacidad')
                        ->siNo()
                        ->helperText('La instalación supera capacidad permitida')
                        ->default(0),
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
                        ->visibility('public')
                        ->fetchFileInformation(false)
                        ->previewable(false)
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

            ...$this->seccionFotosExistentes('8'),
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
                    Forms\Components\ToggleButtons::make('acompanamiento_tecnico')
                        ->label('¿Hubo acompañamiento técnico?')
                        ->siNo()
                        ->default(0)
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
                        ->fetchFileInformation(false)
                        ->previewable(false)
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

            ...$this->seccionFotosExistentes('7'),
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
                            $label = TipologiaOrden::find($this->orden->tipo_gestion_legal ?? null)?->nombre;

                            return $label ?? ($this->orden->tipo_gestion_legal ?? '—');
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
                        ->visibility('public')
                        ->directory('reportes/'.$this->orden->numero_orden)
                        ->fetchFileInformation(false)
                        ->previewable(false)
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

            ...$this->seccionFotosExistentes('5'),
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
                        ->visibility('public')
                        ->directory('reportes/'.$this->orden->numero_orden)
                        ->fetchFileInformation(false)
                        ->previewable(false)
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

            ...$this->seccionFotosExistentes('4'),
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
                    Forms\Components\ToggleButtons::make('hay_novedad')
                        ->label('¿Hay novedades?')
                        ->siNo()
                        ->helperText('Indica si hubo novedades durante el turno')
                        ->default(0)
                        ->live(),
                    Forms\Components\Textarea::make('descripcion_novedad')
                        ->label('Descripción de las Novedades')
                        ->placeholder('Detalla las novedades observadas durante el turno...')
                        ->rows(4)
                        ->required(fn ($get) => (bool) $get('hay_novedad'))
                        ->visible(fn ($get) => (bool) $get('hay_novedad'))
                        ->columnSpanFull(),
                    Forms\Components\ToggleButtons::make('hay_reincidencia')
                        ->label('¿Hay reincidencia detectada?')
                        ->siNo()
                        ->default(0)
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
                        ->visibility('public')
                        ->directory('reportes/'.$this->orden->numero_orden)
                        ->fetchFileInformation(false)
                        ->previewable(false)
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

            ...$this->seccionFotosExistentes('6'),
        ];
    }

    /** @return array<int, Section> */
    private function auditoriaCampoSchema(): array
    {
        $subtipo = match ($this->orden->tipo_auditoria_campo) {
            'visita_calidad_emergencia' => 'Visita de Calidad — Emergencia',
            'visita_calidad_trabajos_varios' => 'Visita de Calidad — Trabajos Varios',
            'visita_calidad_medidores_digitales' => 'Visita de Calidad — Medidores Digitales',
            'visita_calidad_suspensiones' => 'Visita de Calidad — Suspensiones',
            default => 'Auditoría de Campo',
        };

        $esMedidoresDigitales = $this->orden->tipo_auditoria_campo === 'visita_calidad_medidores_digitales';

        if ($this->orden->tipo_auditoria_campo === 'visita_calidad_suspensiones') {
            return $this->suspensionesSchema($subtipo);
        }

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
                    Forms\Components\Placeholder::make('tecnico_display')
                        ->label('Nombre del Técnico')
                        ->content($this->orden->tecnico ?? '—'),
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

            ...($esMedidoresDigitales ? [] : [
                Section::make('3. Validación de la Atención y Ejecución')
                    ->icon('heroicon-o-check-circle')
                    ->columns(2)
                    ->schema([
                        Forms\Components\ToggleButtons::make('recibio_visita')
                            ->label('¿Recibió la visita del técnico?')
                            ->siNo()
                            ->default(0),
                        Forms\Components\ToggleButtons::make('se_realizo_revision')
                            ->label('¿Se realizó revisión técnica o trabajos?')
                            ->siNo()
                            ->default(0),
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
                        Forms\Components\ToggleButtons::make('se_detecto_fuga')
                            ->label('¿Se detectó fuga o falla?')
                            ->siNo()
                            ->default(0)
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
                        Forms\Components\ToggleButtons::make('problema_persiste')
                            ->label('¿Actualmente el problema persiste?')
                            ->siNo()
                            ->default(0),
                        Forms\Components\ToggleButtons::make('evidencia_reparacion')
                            ->label('¿Se evidencia la reparación en el punto?')
                            ->siNo()
                            ->default(0),
                        Forms\Components\ToggleButtons::make('servicio_correcto')
                            ->label('¿El servicio funciona correctamente?')
                            ->siNo()
                            ->default(0)
                            ->columnSpanFull(),
                    ]),

                Section::make('5. Materiales y Cobros')
                    ->icon('heroicon-o-currency-dollar')
                    ->columns(2)
                    ->schema([
                        Forms\Components\ToggleButtons::make('se_usaron_materiales')
                            ->label('¿Se utilizaron materiales?')
                            ->siNo()
                            ->default(0)
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
                        Forms\Components\ToggleButtons::make('usuario_asumio_costo')
                            ->label('¿El usuario asumió algún costo?')
                            ->siNo()
                            ->default(0)
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
                        Forms\Components\ToggleButtons::make('entrego_factura')
                            ->label('¿Se entregó factura, IVT o soporte de cobro?')
                            ->siNo()
                            ->default(0)
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
                        Forms\Components\ToggleButtons::make('informaron_no_estaba')
                            ->label('¿Le informaron erróneamente que no estaba en la vivienda?')
                            ->siNo()
                            ->default(0),
                    ]),
            ]),

            ...($esMedidoresDigitales ? [
                Section::make('Validación y Hallazgos — Medidores Digitales')
                    ->icon('heroicon-o-cpu-chip')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('tecnico_atendio_medidor')
                            ->label('Técnico que atendió')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('numero_medidor_instalado')
                            ->label('Número de medidor instalado')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\Select::make('resultado_medidor')
                            ->label('Resultado de la auditoría')
                            ->options([
                                'conforme' => 'Conforme',
                                'no_conforme' => 'No conforme',
                                'seguimiento_urgente' => 'Requiere seguimiento urgente',
                            ])
                            ->required()
                            ->placeholder('Seleccionar')
                            ->columnSpanFull(),
                        Forms\Components\ToggleButtons::make('solicito_dinero_medidor')
                            ->label('¿Solicitó, insinuó o recibió dinero?')
                            ->siNo()
                            ->default(0),
                        Forms\Components\ToggleButtons::make('gestion_conforme_medidor')
                            ->label('¿Gestión conforme a procedimientos?')
                            ->siNo()
                            ->default(0),
                        Forms\Components\ToggleButtons::make('requiere_seguimiento_medidor')
                            ->label('¿Requiere seguimiento?')
                            ->siNo()
                            ->default(0),
                        Forms\Components\ToggleButtons::make('informo_gratuidad_medidor')
                            ->label('¿Informó gratuidad medidor/instalación?')
                            ->siNo()
                            ->default(0),
                        Forms\Components\Textarea::make('hallazgos_medidor')
                            ->label('Hallazgos / observaciones')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ] : []),

            Section::make('7. Autorización y Firmas')
                ->icon('heroicon-o-pencil')
                ->columns(2)
                ->schema([
                    Forms\Components\ToggleButtons::make('autoriza')
                        ->label('¿El usuario autoriza la recolección de esta información?')
                        ->siNo()
                        ->rules(['accepted'])
                        ->validationMessages(['accepted' => 'Debes contar con la autorización del usuario para enviar el reporte.'])
                        ->default(0)
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
                        ->visibility('public')
                        ->directory('reportes/'.$this->orden->numero_orden)
                        ->fetchFileInformation(false)
                        ->previewable(false)
                        ->multiple()
                        ->acceptedFileTypes(['image/*'])
                        ->maxFiles(10)
                        ->maxSize(5120)
                        ->reorderable()
                        ->extraInputAttributes(['capture' => 'environment'])
                        ->columnSpanFull(),
                    Forms\Components\TagsInput::make('urls_evidencia')
                        ->label('URLs de evidencia / documentos')
                        ->placeholder('Pega una URL y presiona Enter')
                        ->hint('Agrega links a documentos, Google Drive, etc.')
                        ->columnSpanFull(),
                ]),

            ...$this->seccionFotosExistentes('10'),
        ];
    }

    /**
     * Sección de solo lectura con las fotos ya guardadas del reporte, agregada al final
     * de cada esquema de formulario únicamente cuando existen. Va separada del campo de
     * subida de fotos nuevas para que el técnico no confunda "ver lo ya enviado" con
     * "adjuntar algo más".
     *
     * @return array<int, Section>
     */
    private function seccionFotosExistentes(string $numero): array
    {
        if ($this->imagenesExistentes === []) {
            return [];
        }

        return [
            Section::make($numero.'. Fotos ya Guardadas')
                ->icon('heroicon-o-photo')
                ->description('Ya quedaron registradas en el reporte. Usa el campo de fotos de arriba solo para agregar más.')
                ->schema([
                    Forms\Components\Placeholder::make('fotos_existentes_galeria')
                        ->hiddenLabel()
                        ->content(fn () => new HtmlString(
                            view('livewire.tecnico.partials.fotos-existentes-galeria', [
                                'urls' => $this->imagenesExistentesUrls(),
                            ])->render()
                        ))
                        ->columnSpanFull(),
                ]),
        ];
    }

    /**
     * Esquema de la Visita de Calidad — Suspensiones.
     * Las fotos de fachada y suspensión se guardan dentro de datos_servicio.
     *
     * @return array<int, Section>
     */
    private function suspensionesSchema(string $subtipo): array
    {
        $directorio = 'reportes/'.$this->orden->numero_orden;

        return [
            Section::make('1. Información General')
                ->icon('heroicon-o-document-text')
                ->columns(2)
                ->schema([
                    Forms\Components\Placeholder::make('subtipo_display')
                        ->label('Subtipo')
                        ->content($subtipo),
                    Forms\Components\Placeholder::make('ot_display')
                        ->label('Número de orden')
                        ->content($this->orden->numero_orden),
                    Forms\Components\Placeholder::make('contrato_display')
                        ->label('Contrato')
                        ->content($this->orden->contrato ?? '—'),
                    Forms\Components\Placeholder::make('tecnico_display')
                        ->label('Nombre del Técnico')
                        ->content($this->orden->tecnico ?? '—'),
                    Forms\Components\Placeholder::make('cliente_display')
                        ->label('Nombre del Usuario')
                        ->content($this->orden->cliente ?? '—'),
                    Forms\Components\Placeholder::make('barrio_display')
                        ->label('Barrio / Municipio')
                        ->content($this->orden->barrio ?? '—'),
                    Forms\Components\Placeholder::make('direccion_display')
                        ->label('Dirección')
                        ->content($this->orden->direccion ?? '—')
                        ->columnSpanFull(),
                    Forms\Components\Placeholder::make('comentario_usuario_display')
                        ->label('Comentario Usuario / Solicitud')
                        ->content($this->orden->comentario_usuario ?? '—'),
                    Forms\Components\Placeholder::make('comentario_tecnico_display')
                        ->label('Comentario Técnico')
                        ->content($this->orden->comentario_tecnico ?? '—'),
                    Forms\Components\Select::make('categoria_campo')
                        ->label('Categoría')
                        ->options([
                            'comercial' => 'Comercial',
                            'residencial' => 'Residencial',
                            'industrial' => 'Industrial',
                        ])
                        ->required()
                        ->placeholder('Seleccionar')
                        ->columnSpanFull(),
                ]),

            Section::make('2. Suspensión')
                ->icon('heroicon-o-no-symbol')
                ->columns(2)
                ->schema([
                    Forms\Components\ToggleButtons::make('suspension_ejecutada')
                        ->label('¿Se ejecutó la suspensión?')
                        ->boolean('Sí', 'No')
                        ->colors([true => 'success', false => 'danger'])
                        ->icons([true => 'heroicon-o-check-circle', false => 'heroicon-o-x-circle'])
                        ->inline()
                        ->required()
                        ->live()
                        ->columnSpanFull(),
                    Forms\Components\Select::make('metodo_suspension')
                        ->label('Método de suspensión')
                        ->options([
                            'sonda_ips' => 'Sonda (Dispositivo IPS)',
                            'sonda_cts' => 'Sonda (Dispositivo CTS)',
                            'taponamiento_acometida' => 'Taponamiento de acometida',
                            'taponamiento_multifamiliar' => 'Taponamiento Multifamiliar',
                        ])
                        ->placeholder('Seleccionar')
                        ->live()
                        ->required()
                        ->visible(fn ($get): bool => (bool) $get('suspension_ejecutada'))
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('serie_sonda')
                        ->label('Serie de la sonda')
                        ->numeric()
                        ->dehydrateStateUsing(fn (mixed $state): ?string => filled($state) ? (string) $state : null)
                        ->required()
                        ->visible(fn ($get): bool => (bool) $get('suspension_ejecutada') && in_array($get('metodo_suspension'), ['sonda_ips', 'sonda_cts'], true)),
                    Forms\Components\TextInput::make('serie_caps')
                        ->label('Serie del CAPS')
                        ->numeric()
                        ->dehydrateStateUsing(fn (mixed $state): ?string => filled($state) ? (string) $state : null)
                        ->required()
                        ->visible(fn ($get): bool => (bool) $get('suspension_ejecutada') && in_array($get('metodo_suspension'), ['sonda_ips', 'sonda_cts'], true)),
                    Forms\Components\Select::make('motivo_no_ejecucion')
                        ->label('Motivo de no ejecución')
                        ->options([
                            'no_ubico_direccion' => 'No se ubicó la dirección',
                            'predio_cerrado' => 'Predio cerrado o desocupado',
                            'usuario_impide' => 'El usuario impide el procedimiento',
                            'riesgo_sitio' => 'Riesgo en sitio (Perro, altura, etc)',
                            'redes_terraza' => 'Redes por terraza',
                            'usuario_taponado' => 'Se encontró el usuario taponado en campo',
                        ])
                        ->placeholder('Seleccionar')
                        ->required()
                        ->visible(fn ($get): bool => $get('suspension_ejecutada') !== null && ! $get('suspension_ejecutada'))
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('justificacion_no_ejecucion')
                        ->label('Justificación')
                        ->helperText('Justificación asociada al motivo seleccionado')
                        ->rows(3)
                        ->required()
                        ->visible(fn ($get): bool => $get('suspension_ejecutada') !== null && ! $get('suspension_ejecutada'))
                        ->columnSpanFull(),
                ]),

            Section::make('3. Estado del medidor')
                ->icon('heroicon-o-cpu-chip')
                ->schema([
                    Forms\Components\Select::make('anomalias_medidor')
                        ->label('Anomalías identificadas')
                        ->helperText('Selecciona las anomalías del catálogo. Déjalo vacío si no hay')
                        ->options(
                            TipoAnomalia::query()
                                ->where('activo', true)
                                ->orderBy('nombre')
                                ->pluck('nombre', 'id')
                        )
                        ->multiple()
                        ->searchable()
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('condicion_sellos')
                        ->label('Condición de los sellos')
                        ->helperText('Describe el estado de los sellos del medidor')
                        ->rows(3)
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('elementos_rotos')
                        ->label('Elementos o componentes rotos')
                        ->helperText('Indica elementos o componentes rotos, si los hay')
                        ->rows(3)
                        ->columnSpanFull(),
                    Forms\Components\Placeholder::make('lectura_referencia_display')
                        ->label('Lectura de referencia (proporcionada)')
                        ->content($this->orden->lectura_referencia ?? '—')
                        ->visible(fn ($get): bool => (bool) $get('suspension_ejecutada'))
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('lectura_medidor')
                        ->label('Lectura del medidor')
                        ->helperText('Debe coincidir con la lectura de referencia')
                        ->numeric()
                        ->dehydrateStateUsing(fn (mixed $state): ?string => filled($state) ? (string) $state : null)
                        ->required()
                        ->visible(fn ($get): bool => (bool) $get('suspension_ejecutada'))
                        ->columnSpanFull(),
                ]),

            Section::make('4. Observaciones')
                ->icon('heroicon-o-pencil-square')
                ->schema([
                    Forms\Components\Textarea::make('observaciones')
                        ->label('Observaciones')
                        ->placeholder('Observaciones adicionales...')
                        ->rows(4)
                        ->columnSpanFull(),
                ]),

            Section::make('5. Soporte Fotográfico')
                ->icon('heroicon-o-camera')
                ->schema([
                    Forms\Components\FileUpload::make('fotos_fachada')
                        ->label('Fotos de la fachada')
                        ->disk('s3')
                        ->directory($directorio)
                        ->fetchFileInformation(false)
                        ->multiple()
                        ->image()
                        ->imagePreviewHeight('100')
                        ->required()
                        ->minFiles(3)
                        ->maxFiles(10)
                        ->maxSize(5120)
                        ->reorderable()
                        ->panelLayout('grid')
                        ->extraInputAttributes(['capture' => 'environment'])
                        ->columnSpanFull(),
                    Forms\Components\FileUpload::make('fotos_suspension')
                        ->label('Fotografías de la suspensión ejecutada y del método aplicado')
                        ->disk('s3')
                        ->directory($directorio)
                        ->fetchFileInformation(false)
                        ->multiple()
                        ->image()
                        ->imagePreviewHeight('100')
                        ->required()
                        ->minFiles(3)
                        ->maxFiles(10)
                        ->maxSize(5120)
                        ->reorderable()
                        ->panelLayout('grid')
                        ->extraInputAttributes(['capture' => 'environment'])
                        ->visible(fn ($get): bool => (bool) $get('suspension_ejecutada'))
                        ->columnSpanFull(),
                    Forms\Components\FileUpload::make('fotos_medidor')
                        ->label('Fotos del estado del medidor (sellos, anomalías, componentes)')
                        ->disk('s3')
                        ->directory($directorio)
                        ->fetchFileInformation(false)
                        ->multiple()
                        ->image()
                        ->imagePreviewHeight('100')
                        ->required()
                        ->minFiles(1)
                        ->maxFiles(10)
                        ->maxSize(5120)
                        ->reorderable()
                        ->panelLayout('grid')
                        ->extraInputAttributes(['capture' => 'environment'])
                        ->visible(fn ($get): bool => (bool) $get('suspension_ejecutada'))
                        ->columnSpanFull(),
                    Forms\Components\FileUpload::make('fotos_no_ejecucion')
                        ->label('Evidencia fotográfica de la causal')
                        ->disk('s3')
                        ->directory($directorio)
                        ->fetchFileInformation(false)
                        ->multiple()
                        ->image()
                        ->imagePreviewHeight('100')
                        ->required()
                        ->minFiles(1)
                        ->maxFiles(10)
                        ->maxSize(5120)
                        ->reorderable()
                        ->panelLayout('grid')
                        ->extraInputAttributes(['capture' => 'environment'])
                        ->visible(fn ($get): bool => $get('suspension_ejecutada') !== null && ! $get('suspension_ejecutada'))
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

        // Descartar referencias temporales de Livewire que no llegaron a subirse a S3
        // (p.ej. un borrador restaurado con un archivo que quedó a medio subir).
        foreach (['imagenes', 'foto_detector_fuga', 'fotos_fachada', 'fotos_suspension', 'fotos_medidor', 'fotos_no_ejecucion'] as $campoArchivo) {
            if (array_key_exists($campoArchivo, $data)) {
                $data[$campoArchivo] = $this->descartarReferenciasTemporales($data[$campoArchivo]);
            }
        }

        if ($this->orden->tipo_auditoria_campo === 'visita_calidad_suspensiones') {
            $data = $this->limpiarCamposSuspensionNoAplicables($data);
        }

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

    /**
     * La foto del detector se guarda dentro de `imagenes` (sin marca propia): si el reporte ya
     * se había enviado con fuga de gas, la foto exigida entonces ya está entre las existentes.
     */
    private function tieneFotoDetectorGuardada(): bool
    {
        return (bool) $this->reporte->hay_fuga_gas && $this->imagenesExistentes !== [];
    }

    private function actualizarUbicacion(): void
    {
        if (blank($this->latitud) || blank($this->longitud)) {
            return;
        }

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
            'requiere_correccion' => false,
        ]);

        $this->actualizarUbicacion();

        $this->orden->update(['estado' => EstadoOrden::EnRevision]);

        $this->notificarCoordinador();

        if (($data['hay_fuga_gas'] ?? false) || ($data['excede_capacidad'] ?? false) || $this->tieneBypass($data)) {
            $this->notificarIncidencias($data, $fotoDetectorFuga, $fotoDetectorData, $fotoDetectorMimeType);
        }

        $this->dispatch('borrador-limpiar');

        $this->redirect(route('tecnico.mis-ordenes'), navigate: true);
    }

    /**
     * Elimina los campos de la rama (ejecutada / no ejecutada) que no aplica, para que un
     * cambio de Sí a No (o viceversa) no deje valores obsoletos guardados.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function limpiarCamposSuspensionNoAplicables(array $data): array
    {
        $camposEjecutada = ['metodo_suspension', 'serie_sonda', 'serie_caps', 'lectura_medidor', 'fotos_suspension', 'fotos_medidor'];
        $camposNoEjecutada = ['motivo_no_ejecucion', 'justificacion_no_ejecucion', 'fotos_no_ejecucion'];

        $descartar = ($data['suspension_ejecutada'] ?? false) ? $camposNoEjecutada : $camposEjecutada;

        if (($data['suspension_ejecutada'] ?? false) && ! in_array($data['metodo_suspension'] ?? null, ['sonda_ips', 'sonda_cts'], true)) {
            $descartar = [...$descartar, 'serie_sonda', 'serie_caps'];
        }

        return array_diff_key($data, array_flip($descartar));
    }

    /**
     * Descarta referencias temporales de Livewire (formato "livewire-file:...") que quedaron
     * en el estado del formulario sin llegar a subirse al disco S3 —por ejemplo, al restaurar
     * un borrador guardado mientras un archivo estaba a medio subir.
     *
     * @param  array<int, string>|string|null  $value
     * @return array<int, string>|string|null
     */
    private function descartarReferenciasTemporales(array|string|null $value): array|string|null
    {
        if (is_string($value)) {
            return str_starts_with($value, 'livewire-file:') ? null : $value;
        }

        if (is_array($value)) {
            return array_values(array_filter(
                $value,
                fn (mixed $path): bool => is_string($path) && ! str_starts_with($path, 'livewire-file:')
            ));
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, string>|null
     */
    private function normalizarImagenes(array $data): ?array
    {
        $imagenes = $this->imagenesExistentes;

        if (! empty($data['imagenes']) && is_array($data['imagenes'])) {
            $imagenes = array_values(array_unique(array_merge($imagenes, $data['imagenes'])));
        }

        if (! empty($data['foto_detector_fuga'])) {
            $fotoDetectorFuga = $data['foto_detector_fuga'];

            if (is_array($fotoDetectorFuga)) {
                $imagenes = array_values(array_unique(array_merge($imagenes, array_values($fotoDetectorFuga))));
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
        unset($datosServicio['observaciones'], $datosServicio['imagenes'], $datosServicio['video'], $datosServicio['geo_referencia'], $datosServicio['actividades']);

        $this->reporte->update([
            'observaciones' => $data['observaciones'] ?? null,
            'imagenes' => ! empty($data['imagenes']) && is_array($data['imagenes'])
                ? array_values(array_unique(array_merge($this->imagenesExistentes, $data['imagenes'])))
                : ($this->imagenesExistentes ?: null),
            'video' => $data['video'] ?? null,
            'geo_referencia' => $data['geo_referencia'] ?? $this->reporte->geo_referencia,
            'datos_servicio' => $datosServicio,
            'actividades' => $data['actividades'] ?? null,
            'requiere_correccion' => false,
        ]);

        $this->actualizarUbicacion();

        // Vigilancia: si la orden sigue activa (En Progreso/En Revisión), la corrección de
        // este turno no debe reabrir toda la orden a revisión ni afectar otros turnos. Solo
        // se fuerza a En Revisión cuando la orden completa estaba Rechazada (flujo previo de
        // un solo reporte marcado, o servicios que no son Vigilancia).
        if (! ($this->orden->tipo_servicio === TipoServicio::Vigilancia && $this->orden->estado !== EstadoOrden::Rechazada)) {
            $this->orden->update(['estado' => EstadoOrden::EnRevision]);
        }

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
    private function tieneBypass(array $data): bool
    {
        $bypassId = TipoAnomalia::where('activo', true)->where('nombre', 'Bypass')->value('id');

        return $bypassId
            && ! empty($data['anomalia'])
            && is_array($data['anomalia'])
            && in_array($bypassId, $data['anomalia'], true);
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

        if ($this->tieneBypass($data)) {
            $incidencias[] = 'Bypass';
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

    /**
     * URLs temporales firmadas para previsualizar las fotos ya guardadas del reporte.
     * Se muestran en una galería de solo lectura, separada del campo de subida de
     * fotos nuevas, para que el técnico no confunda "ver lo ya enviado" con "adjuntar".
     *
     * @return array<int, string>
     */
    public function imagenesExistentesUrls(): array
    {
        return collect($this->imagenesExistentes)
            ->map(fn (string $path) => ArchivoUrl::temporal($path))
            ->all();
    }

    public function render(): View
    {
        return view('livewire.tecnico.reporte-edit');
    }
}
