<?php

namespace App\Livewire\Tecnico;

use App\Enums\EstadoOrden;
use App\Enums\PrioridadOrden;
use App\Mail\OrdenIniciada;
use App\Models\OrdenJob;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
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
#[Title('Mis Órdenes')]
class MisOrdenesIndex extends Component implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        return $table
            ->query(function () {
                $query = OrdenJob::query()
                    ->whereIn('estado', [
                        EstadoOrden::Pendiente->value,
                        EstadoOrden::Asignada->value,
                        EstadoOrden::EnProgreso->value,
                        EstadoOrden::EnRevision->value,
                        EstadoOrden::Rechazada->value,
                    ]);

                if (! auth()->user()->hasRole('Administrador')) {
                    $query->where('personals_id', auth()->user()->personals_id);
                }

                return $query;
            })
            ->columns([
                Tables\Columns\TextColumn::make('numero_orden')
                    ->label('N° Orden')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('cliente')
                    ->label('Cliente')
                    ->searchable(),
                Tables\Columns\TextColumn::make('direccion')
                    ->label('Dirección')
                    ->searchable(),
                Tables\Columns\TextColumn::make('barrio')
                    ->label('Barrio'),
                Tables\Columns\TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof EstadoOrden ? $state->label() : $state)
                    ->color(fn ($state) => $state instanceof EstadoOrden ? $state->color() : 'gray'),
                Tables\Columns\TextColumn::make('prioridad')
                    ->label('Prioridad')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof PrioridadOrden ? $state->label() : $state)
                    ->color(fn ($state) => $state instanceof PrioridadOrden ? $state->color() : 'gray'),
                Tables\Columns\TextColumn::make('fecha_limite')
                    ->label('Fecha Límite')
                    ->date('d/m/Y')
                    ->sortable()
                    ->placeholder('—'),
            ])
            ->headerActions([])
            ->actions([
                Action::make('iniciar')
                    ->label('Iniciar')
                    ->icon('heroicon-o-play')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Iniciar orden')
                    ->modalDescription(fn (OrdenJob $record): string => "¿Confirmas que vas a iniciar la atención de la orden {$record->numero_orden}?")
                    ->modalSubmitActionLabel('Sí, iniciar')
                    ->visible(fn (OrdenJob $record): bool => $record->estado === EstadoOrden::Asignada)
                    ->action(function (OrdenJob $record): void {
                        $record->update(['estado' => EstadoOrden::EnProgreso]);
                        $this->notificarCreador($record);
                    }),

                Action::make('reportar')
                    ->label('Crear Reporte')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->color('primary')
                    ->visible(fn (OrdenJob $record): bool => $record->estado === EstadoOrden::EnProgreso &&
                        $record->reportes()->doesntExist()
                    )
                    ->url(fn (OrdenJob $record): string => route('tecnico.ordenes.reportar', $record)),

                Action::make('editar_reporte')
                    ->label('Editar Reporte')
                    ->icon('heroicon-o-pencil-square')
                    ->color('danger')
                    ->visible(fn (OrdenJob $record): bool => $record->estado === EstadoOrden::Rechazada)
                    ->url(fn (OrdenJob $record): string => route('tecnico.ordenes.reporte.editar', $record)),
            ])
            ->emptyStateHeading('Sin órdenes asignadas')
            ->emptyStateDescription('No tienes órdenes activas en este momento.')
            ->emptyStateIcon('heroicon-o-clipboard-document-list')
            ->defaultSort('fecha_limite', 'asc');
    }

    protected function notificarCreador(OrdenJob $orden): void
    {
        $email = $orden->creadoPor?->email;
        $tecnico = $orden->personal;

        if (! $email || ! $tecnico) {
            return;
        }

        try {
            Mail::to($email)->send(new OrdenIniciada($orden, $tecnico));
        } catch (\Exception $e) {
            Log::error('No se pudo enviar notificación de orden iniciada.', [
                'orden' => $orden->numero_orden,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function render(): View
    {
        return view('livewire.tecnico.mis-ordenes-index');
    }
}
