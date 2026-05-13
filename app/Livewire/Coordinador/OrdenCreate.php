<?php

namespace App\Livewire\Coordinador;

use App\Enums\EstadoOrden;
use App\Enums\PrioridadOrden;
use App\Enums\TipoServicio;
use App\Mail\OrdenAsignada;
use App\Models\OrdenJob;
use App\Models\personals;
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
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Nueva Orden de Trabajo')]
/**
 * Formulario de creación de una nueva orden de trabajo.
 * El formulario se adapta dinámicamente según el tipo de servicio seleccionado.
 */
class OrdenCreate extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'tipo_servicio' => TipoServicio::Fraudes->value,
            'prioridad' => PrioridadOrden::Media->value,
            'estado' => EstadoOrden::Pendiente->value,
            'estado_servicio' => true,
        ]);
    }

    public function form(Schema $form): Schema
    {
        $tecnicos = personals::query()
            ->select(['id', 'nombres', 'apellidos'])
            ->get()
            ->mapWithKeys(fn ($p) => [$p->id => "{$p->nombres} {$p->apellidos}"]);

        return $form
            ->schema([
                // ── Tipo de Servicio ──────────────────────────────────────
                Section::make('Tipo de Servicio')
                    ->schema([
                        Forms\Components\Select::make('tipo_servicio')
                            ->label('Tipo de Servicio')
                            ->options(
                                collect(TipoServicio::cases())
                                    ->mapWithKeys(fn (TipoServicio $case) => [$case->value => $case->label()])
                            )
                            ->required()
                            ->live()
                            ->default(TipoServicio::Fraudes->value),
                    ]),

                // ── Sección: Fraudes ──────────────────────────────────────
                Section::make('Datos del Punto de Servicio')
                    ->columns(2)
                    ->visible(fn ($get) => $get('tipo_servicio') === TipoServicio::Fraudes->value)
                    ->schema([
                        Forms\Components\TextInput::make('contrato')
                            ->label('Contrato')
                            ->required(fn ($get) => $get('tipo_servicio') === TipoServicio::Fraudes->value)
                            ->maxLength(255),
                        Forms\Components\TextInput::make('cliente')
                            ->label('Cliente')
                            ->required(fn ($get) => $get('tipo_servicio') === TipoServicio::Fraudes->value)
                            ->maxLength(255),
                        Forms\Components\TextInput::make('direccion')
                            ->label('Dirección')
                            ->required(fn ($get) => $get('tipo_servicio') === TipoServicio::Fraudes->value)
                            ->maxLength(255),
                        Forms\Components\TextInput::make('barrio')
                            ->label('Barrio')
                            ->required(fn ($get) => $get('tipo_servicio') === TipoServicio::Fraudes->value)
                            ->maxLength(255),
                        Forms\Components\TextInput::make('medidor')
                            ->label('N° Medidor')
                            ->required(fn ($get) => $get('tipo_servicio') === TipoServicio::Fraudes->value)
                            ->maxLength(255),
                        Forms\Components\Select::make('tipo_medidor')
                            ->label('Tipo de Medidor')
                            ->options([
                                'por_definir' => 'Por definir',
                                'analogico' => 'Analógico',
                                'digital' => 'Digital',
                            ])
                            ->required(fn ($get) => $get('tipo_servicio') === TipoServicio::Fraudes->value)
                            ->placeholder('Seleccionar'),
                        Forms\Components\TextInput::make('ciclo')
                            ->label('Ciclo')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),

                // ── Sección: Investigación ────────────────────────────────
                Section::make('Datos de la Investigación')
                    ->columns(1)
                    ->visible(fn ($get) => $get('tipo_servicio') === TipoServicio::Investigacion->value)
                    ->schema([
                        Forms\Components\Select::make('tipo_investigacion')
                            ->label('Tipo de Investigación')
                            ->options([
                                'interna' => 'Interna',
                                'contratista' => 'Contratista',
                                'Terceros' => 'Terceros',
                            ])
                            ->required(fn ($get) => $get('tipo_servicio') === TipoServicio::Investigacion->value)
                            ->placeholder('Seleccionar')
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('antecedente')
                            ->label('Antecedentes')
                            ->required(fn ($get) => $get('tipo_servicio') === TipoServicio::Investigacion->value)
                            ->rows(5)
                            ->cols(5)
                            ->maxLength(1000),
                        Forms\Components\Textarea::make('objetivo')
                            ->label('Objetivo de la Investigación')
                            ->required(fn ($get) => $get('tipo_servicio') === TipoServicio::Investigacion->value)
                            ->rows(5)
                            ->cols(5)
                            ->maxLength(1000),
                    ]),

                // ── Sección: Gestión Legal ────────────────────────────────
                Section::make('Datos de Gestión Legal')
                    ->columns(1)
                    ->visible(fn ($get) => $get('tipo_servicio') === TipoServicio::GestionLegal->value)
                    ->schema([
                        Forms\Components\Select::make('tipo_gestion_legal')
                            ->label('Tipo de Gestión Legal')
                            ->options([
                                'capturas' => 'Capturas Criminales',
                                'bandas' => 'Desarticulación de Bandas',
                                'querella' => 'Querella Penal',
                                'seguimiento_denuncia' => 'Seguimiento a Denuncia',
                                'diligencia_judicial' => 'Diligencia Judicial',
                                'incautacion_equipos' => 'Incautación de Equipos',
                            ])
                            ->required(fn ($get) => $get('tipo_servicio') === TipoServicio::GestionLegal->value)
                            ->placeholder('Seleccionar'),
                        Forms\Components\TextInput::make('numero_noticia_criminal')
                            ->label('N° Noticia Criminal')
                            ->maxLength(255),
                        Forms\Components\Textarea::make('objetivo')
                            ->label('Objetivo de la Gestión Legal')
                            ->required(fn ($get) => $get('tipo_servicio') === TipoServicio::GestionLegal->value)
                            ->rows(5)
                            ->cols(5)
                            ->maxLength(1000),
                    ]),

                // ── Sección: Vigilancia ───────────────────────────────────
                Section::make('Datos del Punto Vigilado')
                    ->columns(2)
                    ->visible(fn ($get) => $get('tipo_servicio') === TipoServicio::Vigilancia->value)
                    ->schema([
                        Forms\Components\TextInput::make('contrato')
                            ->label('Contrato')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('cliente')
                            ->label('Nombre del Comercio / Cliente')
                            ->required(fn ($get) => $get('tipo_servicio') === TipoServicio::Vigilancia->value)
                            ->maxLength(255),
                        Forms\Components\TextInput::make('direccion')
                            ->label('Dirección')
                            ->required(fn ($get) => $get('tipo_servicio') === TipoServicio::Vigilancia->value)
                            ->maxLength(255),
                        Forms\Components\TextInput::make('barrio')
                            ->label('Barrio / Municipio')
                            ->maxLength(255),
                        Forms\Components\Textarea::make('objetivo')
                            ->label('Objetivo de la Investigación')
                            ->required(fn ($get) => $get('tipo_servicio') === TipoServicio::Vigilancia->value)
                            ->rows(5)
                            ->columnSpanFull()
                            ->cols(5)
                            ->maxLength(1000),
                    ]),
                // ── Sección: Gestión de Riesgo ─────────────────────────────
                Section::make('Datos de Gestión de Riesgo')
                    ->columns(1)
                    ->visible(fn ($get) => $get('tipo_servicio') === TipoServicio::Gestion_Riesgo->value)
                    ->schema([
                        Forms\Components\Textarea::make('objetivo')
                            ->label('Objetivos')
                            ->required(fn ($get) => $get('tipo_servicio') === TipoServicio::Gestion_Riesgo->value)
                            ->rows(5)
                            ->cols(5)
                            ->maxLength(1000),
                    ]),
                // ── Sección: Actividad A Realizar ─────────────────────────
                Section::make('Actividad A Realizar')
                    ->icon('heroicon-o-check-badge')
                    ->schema([
                        Repeater::make('actividades')
                            ->label('Actividades por realizar')
                            ->schema([
                                Forms\Components\Textarea::make('descripcion')
                                    ->autosize()
                                    ->label('Actividades')
                                    ->columnSpanFull()
                                    ->placeholder('Describe las actividades realizadas durante la investigación...')
                                    ->required(),
                            ])
                            ->columns(2)
                            ->collapsible()
                            ->createItemButtonLabel('Agregar Actividad')]),
                // ── Documentos  ──────────────────────────────────────────
                Section::make('Gestion de Documentos')
                    ->schema([
                        Forms\Components\FileUpload::make('documentos')
                            ->label('Subir documentos')
                            ->disk('s3')
                            ->directory('ordenes/temp')
                            ->visibility('public')
                            ->multiple()
                            ->imagePreviewHeight('120')
                            ->maxFiles(10)
                            ->maxSize(5120)
                            ->reorderable()
                            ->panelLayout('grid')
                            ->columnSpanFull(),
                    ]),

                // ── Gestión ───────────────────────────────────────────────
                Section::make('Gestión de la Orden')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('prioridad')
                            ->label('Prioridad')
                            ->options(
                                collect(PrioridadOrden::cases())
                                    ->mapWithKeys(fn (PrioridadOrden $case) => [$case->value => $case->label()])
                            )
                            ->required(),
                        Forms\Components\Select::make('estado')
                            ->label('Estado')
                            ->options(
                                collect(EstadoOrden::cases())
                                    ->mapWithKeys(fn (EstadoOrden $case) => [$case->value => $case->label()])
                            )
                            ->required(),
                        Forms\Components\DateTimePicker::make('fecha_limite')
                            ->label('Fecha Límite')
                            ->nullable(),
                        Forms\Components\Select::make('personals_id')
                            ->label(fn ($get) => $get('tipo_servicio') === TipoServicio::Vigilancia->value ? 'Investigador Asignado' : 'Personal Asignado')
                            ->options($tecnicos)
                            ->searchable()
                            ->nullable()
                            ->placeholder('Sin asignar')
                            ->columnSpanFull(),
                        Forms\Components\Toggle::make('estado_servicio')
                            ->label('Servicio Activo')
                            ->default(true)
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    /**
     * Persiste la nueva orden y mueve las fotos del directorio temporal al definitivo en S3.
     * Envía notificación al técnico si fue asignado en la creación.
     */
    public function guardar(): void
    {
        $data = $this->form->getState();

        $documentosTemp = $data['documentos'] ?? [];
        unset($data['documentos']);

        $orden = OrdenJob::create($data);

        if (! empty($documentosTemp)) {
            $orden->update(['documentos' => $this->moverdocumentos($documentosTemp, $orden->numero_orden)]);
        }

        if (! empty($data['personals_id'])) {
            $this->notificarTecnico($orden);
        }

        session()->flash('success', "Orden {$orden->numero_orden} creada correctamente.");

        $this->redirect(route('operaciones.ordenes-trabajo'), navigate: true);
    }

    /** @param array<string> $documentos */
    private function moverdocumentos(array $documentos, string $numeroOrden): array
    {
        $directorio = "ordenes/{$numeroOrden}";
        $resultado = [];

        foreach ($documentos as $rutaTemp) {
            $nombreArchivo = basename($rutaTemp);
            $rutaFinal = "{$directorio}/{$nombreArchivo}";

            try {
                if (Storage::disk('s3')->exists($rutaTemp)) {
                    Storage::disk('s3')->move($rutaTemp, $rutaFinal);
                }
            } catch (\Exception $e) {
                Log::error('No se pudo mover documento de orden.', [
                    'origen' => $rutaTemp,
                    'destino' => $rutaFinal,
                    'error' => $e->getMessage(),
                ]);
                $rutaFinal = $rutaTemp;
            }

            $resultado[] = $rutaFinal;
        }

        return $resultado;
    }

    private function notificarTecnico(OrdenJob $orden): void
    {
        $email = $orden->personal?->usuario?->email;

        if (! $email) {
            return;
        }

        try {
            Mail::to($email)->send(new OrdenAsignada($orden, $orden->personal));
        } catch (\Exception $e) {
            Log::error('No se pudo enviar notificación de orden asignada.', [
                'orden' => $orden->numero_orden,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function render(): View
    {
        return view('livewire.coordinador.orden-create');
    }
}
