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
use Illuminate\Support\Facades\Storage;
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
                Action::make('exportar')
                    ->label('Exportar CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->url(route('operaciones.exportar-ordenes'))
                    ->openUrlInNewTab(),
                Action::make('create')
                    ->label('Nueva Orden')
                    ->icon('heroicon-o-plus')
                    ->modalHeading('Crear Orden de Trabajo')
                    ->form($this->ordenFormSchema())
                    ->action(function (array $data): void {
                        // Extract fotos before create; they're in temp dir on S3
                        $fotosTemp = $data['fotos'] ?? [];
                        unset($data['fotos']);

                        $orden = OrdenJob::create($data);

                        if (! empty($fotosTemp)) {
                            $orden->update([
                                'fotos' => $this->moverFotos($fotosTemp, $orden->numero_orden),
                            ]);
                        }

                        if (! empty($data['personals_id'])) {
                            $this->notificarTecnico($orden);
                        }
                    }),
            ])
            ->actions([
                Action::make('revisar')
                    ->label('Revisar')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->color('warning')
                    ->visible(fn (OrdenJob $record): bool => $record->estado === EstadoOrden::EnRevision)
                    ->url(fn (OrdenJob $record): string => route('operaciones.ordenes-trabajo.revisar', $record)),
                Action::make('view')
                    ->label('Ver')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn (OrdenJob $record): string => route('operaciones.ordenes-trabajo.show', $record)),
                EditAction::make()
                    ->modalHeading(fn (OrdenJob $record): string => "Editar Orden: {$record->numero_orden}")
                    ->form(fn (OrdenJob $record) => $this->ordenFormSchema($record))
                    ->visible(fn (OrdenJob $record): bool => $record->estado === EstadoOrden::Pendiente)
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
                DeleteAction::make()
                    ->visible(fn (OrdenJob $record): bool => $record->estado === EstadoOrden::Pendiente),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * @param  OrdenJob|null  $record  Cuando se edita, para apuntar al directorio correcto en S3.
     */
    protected function ordenFormSchema(?OrdenJob $record = null): array
    {
        $tecnicos = personals::query()
            ->select(['id', 'nombres', 'apellidos'])
            ->get()
            ->mapWithKeys(fn ($p) => [$p->id => "{$p->nombres} {$p->apellidos}"]);

        $fotoDirectorio = $record
            ? "ordenes/{$record->numero_orden}"
            : 'ordenes/temp';

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
            Section::make('Fotos de la Orden')
                ->schema([
                    Forms\Components\FileUpload::make('fotos')
                        ->label('Subir fotos')
                        ->disk('s3')
                        ->directory($fotoDirectorio)
                        ->visibility('public')
                        ->multiple()
                        ->image()
                        ->imagePreviewHeight('120')
                        ->maxFiles(10)
                        ->maxSize(5120) // 5 MB por foto
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

    /**
     * Mueve fotos desde el directorio temporal al directorio definitivo de la orden en S3.
     *
     * @param  array<string>  $fotos
     * @return array<string>
     */
    protected function moverFotos(array $fotos, string $numeroOrden): array
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
