<?php

namespace App\Livewire\Coordinador;

use App\Enums\EstadoOrden;
use App\Enums\PrioridadOrden;
use App\Mail\OrdenAsignada;
use App\Models\OrdenJob;
use App\Models\personals;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Órdenes de Trabajo')]
class OrdenesTrabajoIndex extends Component implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        return $table
            ->query(OrdenJob::query()->with('personal'))
            ->columns([
                Tables\Columns\TextColumn::make('numero_orden')
                    ->label('N° Orden')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('cliente')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('contrato')
                    ->label('Contrato')
                    ->searchable(),
                Tables\Columns\TextColumn::make('barrio')
                    ->label('Barrio')
                    ->searchable(),
                Tables\Columns\TextColumn::make('prioridad')
                    ->label('Prioridad')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof PrioridadOrden ? $state->label() : $state)
                    ->color(fn ($state) => $state instanceof PrioridadOrden ? $state->color() : 'gray'),
                Tables\Columns\TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof EstadoOrden ? $state->label() : $state)
                    ->color(fn ($state) => $state instanceof EstadoOrden ? $state->color() : 'gray'),
                Tables\Columns\TextColumn::make('personal.nombres')
                    ->label('Técnico Asignado')
                    ->formatStateUsing(fn ($state, OrdenJob $record): string => $record->personal
                            ? "{$record->personal->nombres} {$record->personal->apellidos}"
                            : '—'
                    )
                    ->searchable(query: fn ($query, string $search) => $query->whereHas(
                        'personal',
                        fn ($q) => $q->where('nombres', 'like', "%{$search}%")
                            ->orWhere('apellidos', 'like', "%{$search}%")
                    ))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('fecha_limite')
                    ->label('Fecha Límite')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('fecha_completado')
                    ->label('Completado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('estado')
                    ->label('Estado')
                    ->options(
                        collect(EstadoOrden::cases())
                            ->mapWithKeys(fn (EstadoOrden $case) => [$case->value => $case->label()])
                    ),
                Tables\Filters\SelectFilter::make('prioridad')
                    ->label('Prioridad')
                    ->options(
                        collect(PrioridadOrden::cases())
                            ->mapWithKeys(fn (PrioridadOrden $case) => [$case->value => $case->label()])
                    ),
                Tables\Filters\TernaryFilter::make('estado_servicio')
                    ->label('Servicio Activo'),
            ])
            ->headerActions([
                Action::make('create')
                    ->label('Nueva Orden')
                    ->icon('heroicon-o-plus')
                    ->modalHeading('Crear Orden de Trabajo')
                    ->form($this->ordenFormSchema())
                    ->action(function (array $data): void {
                        $orden = OrdenJob::create($data);

                        if (! empty($data['personals_id'])) {
                            $this->notificarTecnico($orden);
                        }
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->modalHeading(fn (OrdenJob $record): string => "Editar Orden: {$record->numero_orden}")
                    ->form($this->ordenFormSchema())
                    ->using(function (OrdenJob $record, array $data): OrdenJob {
                        $tecnicoAnterior = $record->personals_id;
                        $record->update($data);

                        if (
                            ! empty($data['personals_id']) &&
                            $data['personals_id'] !== $tecnicoAnterior
                        ) {
                            $record->load('personal');
                            $this->notificarTecnico($record);
                        }

                        return $record;
                    }),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    protected function ordenFormSchema(): array
    {
        $tecnicos = personals::query()
            ->select(['id', 'nombres', 'apellidos'])
            ->get()
            ->mapWithKeys(fn ($p) => [$p->id => "{$p->nombres} {$p->apellidos}"]);

        return [
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
            Section::make('Ubicación')
                ->columns(2)
                ->collapsed()
                ->schema([
                    Forms\Components\TextInput::make('latitud')
                        ->label('Latitud')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('longitud')
                        ->label('Longitud')
                        ->maxLength(255),
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
                        ->required()
                        ->default(PrioridadOrden::Media->value),
                    Forms\Components\Select::make('estado')
                        ->label('Estado')
                        ->options(
                            collect(EstadoOrden::cases())
                                ->mapWithKeys(fn (EstadoOrden $case) => [$case->value => $case->label()])
                        )
                        ->required()
                        ->default(EstadoOrden::Pendiente->value),
                    Forms\Components\DateTimePicker::make('fecha_asignacion')
                        ->label('Fecha de Asignación')
                        ->nullable(),
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
        ];
    }

    protected function notificarTecnico(OrdenJob $orden): void
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
        return view('livewire.coordinador.ordenes-trabajo-index');
    }
}
