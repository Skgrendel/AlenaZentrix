<?php

namespace App\Livewire\Tecnico;

use App\Enums\EstadoOrden;
use App\Enums\PrioridadOrden;
use App\Enums\TipoServicio;
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
/**
 * Listado de órdenes activas para el técnico: incluye sus propias órdenes asignadas
 * y todas las órdenes de Vigilancia abiertas (sin técnico fijo).
 */
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

                if (! auth()->user()->can('ordenes.ver')) {
                    $query->where(function ($q) {
                        // Órdenes asignadas al técnico
                        $q->where('personals_id', auth()->user()->personals_id)
                            // O vigilancias abiertas (sin asignado)
                            ->orWhere('tipo_servicio', TipoServicio::Vigilancia->value);
                    });
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
                Tables\Columns\TextColumn::make('tipo_servicio')
                    ->label('Servicio')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof TipoServicio ? $state->label() : $state)
                    ->color(fn ($state) => $state instanceof TipoServicio ? $state->color() : 'gray'),
                Tables\Columns\TextColumn::make('cliente')
                    ->label('Cliente / Referencia')
                    ->searchable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('direccion')
                    ->label('Dirección')
                    ->searchable()
                    ->placeholder('—'),
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
                    ->visible(fn (OrdenJob $record): bool => $record->estado === EstadoOrden::Asignada &&
                        $record->tipo_servicio !== TipoServicio::Vigilancia
                    )
                    ->action(function (OrdenJob $record): void {
                        $record->update(['estado' => EstadoOrden::EnProgreso]);
                        $this->notificarCreador($record);
                    }),

                Action::make('reportar')
                    ->label('Agregar Reporte')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->color('primary')
                    ->visible(fn (OrdenJob $record): bool => $record->estado === EstadoOrden::EnProgreso &&
                        (
                            $record->tipo_servicio === TipoServicio::Vigilancia ||
                            $record->reportes()->doesntExist()
                        )
                    )
                    ->url(fn (OrdenJob $record): string => route('tecnico.ordenes.reportar', $record)),

                Action::make('cerrar_vigilancia')
                    ->label('Cerrar Vigilancia')
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Cerrar orden de vigilancia')
                    ->modalDescription(fn (OrdenJob $record): string => "¿Confirmas que deseas cerrar la vigilancia de la orden {$record->numero_orden}? No podrás agregar más reportes.")
                    ->modalSubmitActionLabel('Sí, cerrar vigilancia')
                    ->visible(fn (OrdenJob $record): bool => $record->tipo_servicio === TipoServicio::Vigilancia &&
                        $record->estado === EstadoOrden::EnProgreso
                    )
                    ->action(fn (OrdenJob $record) => $record->update(['estado' => EstadoOrden::EnRevision])),

                Action::make('editar_reporte')
                    ->label('Editar Reporte')
                    ->icon('heroicon-o-pencil-square')
                    ->color('danger')
                    ->visible(fn (OrdenJob $record): bool => $record->estado === EstadoOrden::Rechazada &&
                        $record->tipo_servicio !== TipoServicio::Vigilancia
                    )
                    ->url(fn (OrdenJob $record): string => route('tecnico.ordenes.reporte.editar', $record)),
            ])
            ->emptyStateHeading('Sin órdenes asignadas')
            ->emptyStateDescription('No tienes órdenes activas en este momento.')
            ->emptyStateIcon('heroicon-o-clipboard-document-list')
            ->defaultSort('fecha_limite', 'asc');
    }

    /** Notifica al coordinador que el técnico inició la atención de la orden. */
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
