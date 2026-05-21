<?php

namespace App\Livewire\Coordinador\RevisionMedidores;

use App\Enums\EstadoOrden;
use App\Enums\TipoServicio;
use App\Mail\OrdenAsignada;
use App\Mail\OrdenesAsignadasResumen;
use App\Models\OrdenJob;
use App\Models\personals;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Revisión de Medidores')]
class RevisionMedidoresIndex extends Component implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        $tecnicos = personals::query()
            ->select(['id', 'nombres', 'apellidos'])
            ->get()
            ->mapWithKeys(fn ($p) => [$p->id => "{$p->nombres} {$p->apellidos}"]);

        $asignacionSchema = [
            Forms\Components\Select::make('personals_id')
                ->label('Investigador')
                ->options($tecnicos)
                ->searchable()
                ->required(),
            Forms\Components\DateTimePicker::make('fecha_asignacion')
                ->label('Fecha de Asignación')
                ->default(now())
                ->required(),
            Forms\Components\DateTimePicker::make('fecha_limite')
                ->label('Fecha Límite')
                ->nullable(),
        ];

        return $table
            ->query(
                OrdenJob::query()
                    ->where('tipo_servicio', TipoServicio::RevisionMedidores->value)
                    ->with(['personal'])
            )
            ->columns([
                Tables\Columns\TextColumn::make('numero_orden')
                    ->label('N° Orden')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('contrato')
                    ->label('Contrato')
                    ->searchable(),
                Tables\Columns\TextColumn::make('cliente')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('direccion')
                    ->label('Dirección')
                    ->searchable()
                    ->formatStateUsing(fn ($state, OrdenJob $record): string => "{$record->direccion}, {$record->barrio}"),
                Tables\Columns\TextColumn::make('medidor')
                    ->label('N° Medidor')
                    ->searchable(),
                Tables\Columns\TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof EstadoOrden ? $state->label() : $state)
                    ->color(fn ($state) => $state instanceof EstadoOrden ? $state->color() : 'gray'),
                Tables\Columns\TextColumn::make('personal.nombres')
                    ->label('Investigador')
                    ->formatStateUsing(fn ($state, OrdenJob $record): string => $record->personal
                        ? "{$record->personal->nombres} {$record->personal->apellidos}"
                        : '—'
                    ),
                Tables\Columns\TextColumn::make('fecha_limite')
                    ->label('Fecha Límite')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('estado')
                    ->label('Estado')
                    ->options(
                        collect(EstadoOrden::cases())
                            ->mapWithKeys(fn (EstadoOrden $e) => [$e->value => $e->label()])
                    ),
            ])
            ->headerActions([
                Action::make('importar')
                    ->label('Importar Órdenes')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->url(route('operaciones.revision-medidores.importar')),
            ])
            ->actions([
                Action::make('asignar')
                    ->label('Asignar')
                    ->icon('heroicon-o-user-plus')
                    ->color('primary')
                    ->visible(fn (OrdenJob $record): bool => $record->estado === EstadoOrden::Pendiente)
                    ->form($asignacionSchema)
                    ->action(function (OrdenJob $record, array $data): void {
                        $record->update([
                            'personals_id' => $data['personals_id'],
                            'estado' => EstadoOrden::Asignada->value,
                            'fecha_asignacion' => $data['fecha_asignacion'],
                            'fecha_limite' => $data['fecha_limite'] ?? null,
                        ]);
                        $this->notificarIndividual($record->fresh(['personal.usuario']));
                    }),
                Action::make('descargar_pdf')
                    ->label('')
                    ->tooltip('Descargar Informe PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->visible(fn (OrdenJob $record): bool => $record->estado === EstadoOrden::Completada
                        && auth()->user()->can('informes')
                    )
                    ->url(fn (OrdenJob $record): string => route(
                        'operaciones.revision-medidores.reporte',
                        [$record, 'reporte' => $record->reportes->sortByDesc('created_at')->first()?->id]
                    ))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('asignar_masivo')
                        ->label('Asignar seleccionadas')
                        ->icon('heroicon-o-user-group')
                        ->color('primary')
                        ->form($asignacionSchema)
                        ->action(function (Collection $records, array $data): void {
                            $records->each(function (OrdenJob $record) use ($data): void {
                                $record->update([
                                    'personals_id' => $data['personals_id'],
                                    'estado' => EstadoOrden::Asignada->value,
                                    'fecha_asignacion' => $data['fecha_asignacion'],
                                    'fecha_limite' => $data['fecha_limite'] ?? null,
                                ]);
                            });
                            $this->notificarMasivo($records, $data['personals_id']);
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->emptyStateHeading('Sin órdenes de revisión')
            ->emptyStateDescription('Importa un archivo para comenzar.')
            ->emptyStateIcon('heroicon-o-clipboard-document-list')
            ->defaultSort('created_at', 'desc');
    }

    private function notificarIndividual(OrdenJob $orden): void
    {
        $email = $orden->personal?->usuario?->email;
        if (! $email) {
            return;
        }
        try {
            Mail::to($email)->send(new OrdenAsignada($orden, $orden->personal));
        } catch (\Exception $e) {
            Log::error('Error enviando OrdenAsignada.', ['orden' => $orden->numero_orden, 'error' => $e->getMessage()]);
        }
    }

    private function notificarMasivo(Collection $ordenes, string $personalsId): void
    {
        $personal = personals::find($personalsId);
        $email = $personal?->usuario?->email;
        if (! $email || ! $personal) {
            return;
        }
        try {
            Mail::to($email)->send(new OrdenesAsignadasResumen($personal, $ordenes));
        } catch (\Exception $e) {
            Log::error('Error enviando OrdenesAsignadasResumen.', ['error' => $e->getMessage()]);
        }
    }

    public function render(): View
    {
        return view('livewire.coordinador.revision-medidores.revision-medidores-index');
    }
}
