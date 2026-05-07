<?php

namespace App\Livewire\Coordinador;

use App\Enums\EstadoOrden;
use App\Enums\PrioridadOrden;
use App\Mail\OrdenAsignada;
use App\Models\OrdenJob;
use App\Models\personals;
use Filament\Forms;
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
class OrdenCreate extends Component implements HasForms
{
    use InteractsWithForms;

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $this->form->fill([
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
                Section::make('Datos del Punto de Servicio')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('contrato')
                            ->label('Contrato')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('cliente')
                            ->label('Cliente')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('direccion')
                            ->label('Dirección')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('barrio')
                            ->label('Barrio')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('medidor')
                            ->label('N° Medidor')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('tipo_medidor')
                            ->label('Tipo de Medidor')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('ciclo')
                            ->label('Ciclo')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),

                Section::make('Fotos de la Orden')
                    ->schema([
                        Forms\Components\FileUpload::make('fotos')
                            ->label('Subir fotos')
                            ->disk('s3')
                            ->directory('ordenes/temp')
                            ->visibility('public')
                            ->multiple()
                            ->image()
                            ->imagePreviewHeight('120')
                            ->maxFiles(10)
                            ->maxSize(5120)
                            ->reorderable()
                            ->panelLayout('grid')
                            ->columnSpanFull(),
                    ]),

                Section::make('Gestión de la Orden')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('titulo')
                            ->label('Título')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('descripcion')
                            ->label('Descripción')
                            ->rows(3)
                            ->columnSpanFull(),
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
                            ->label('Técnico Asignado')
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

    public function guardar(): void
    {
        $data = $this->form->getState();

        $fotosTemp = $data['fotos'] ?? [];
        unset($data['fotos']);

        $orden = OrdenJob::create($data);

        if (! empty($fotosTemp)) {
            $orden->update(['fotos' => $this->moverFotos($fotosTemp, $orden->numero_orden)]);
        }

        if (! empty($data['personals_id'])) {
            $this->notificarTecnico($orden);
        }

        session()->flash('success', "Orden {$orden->numero_orden} creada correctamente.");

        $this->redirect(route('operaciones.ordenes-trabajo'), navigate: true);
    }

    /** @param array<string> $fotos */
    private function moverFotos(array $fotos, string $numeroOrden): array
    {
        $directorio = "ordenes/{$numeroOrden}";
        $resultado = [];

        foreach ($fotos as $rutaTemp) {
            $nombreArchivo = basename($rutaTemp);
            $rutaFinal = "{$directorio}/{$nombreArchivo}";

            try {
                if (Storage::disk('s3')->exists($rutaTemp)) {
                    Storage::disk('s3')->move($rutaTemp, $rutaFinal);
                }
            } catch (\Exception $e) {
                Log::error('No se pudo mover foto de orden.', [
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
