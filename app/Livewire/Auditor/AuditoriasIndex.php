<?php

namespace App\Livewire\Auditor;

use App\Enums\EstadoAuditoria;
use App\Models\auditoria;
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
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Auditorías')]
/**
 * Listado de auditorías pendientes y completadas para el rol Auditor.
 * Solo se generan auditorías para órdenes de tipo Fraudes al completarse.
 */
class AuditoriasIndex extends Component implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                auditoria::query()
                    ->with(['reporte.ordenJob.personal'])
            )
            ->columns([
                Tables\Columns\TextColumn::make('numero_orden')
                    ->label('N° Orden')
                    ->badge()
                    ->color('success')
                    ->getStateUsing(fn (auditoria $record): string => $record->reporte?->ordenJob?->numero_orden ?? '—'),
                Tables\Columns\TextColumn::make('cliente')
                    ->label('Cliente')
                    ->getStateUsing(fn (auditoria $record): string => $record->reporte?->ordenJob?->cliente ?? '—'),
                Tables\Columns\TextColumn::make('barrio')
                    ->label('Barrio')
                    ->getStateUsing(fn (auditoria $record): string => $record->reporte?->ordenJob?->barrio ?? '—'),
                Tables\Columns\TextColumn::make('tecnico')
                    ->label('Técnico')
                    ->getStateUsing(function (auditoria $record): string {
                        $personal = $record->reporte?->ordenJob?->personal;

                        return $personal
                            ? "{$personal->nombres} {$personal->apellidos}"
                            : '—';
                    }),
                Tables\Columns\TextColumn::make('estado')
                    ->label('Estado Auditoría')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof EstadoAuditoria ? $state->label() : $state)
                    ->color(fn ($state) => $state instanceof EstadoAuditoria ? $state->color() : 'gray'),
                Tables\Columns\TextColumn::make('fecha_completado')
                    ->label('Fecha Completado')
                    ->getStateUsing(fn (auditoria $record): ?string => $record->reporte?->ordenJob?->fecha_completado?->format('d/m/Y H:i')),
                Tables\Columns\TextColumn::make('fecha_auditoria')
                    ->label('Fecha Auditoría')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('—'),
            ])
            ->actions([
                Action::make('auditar')
                    ->label('Auditar')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->color('primary')
                    ->visible(fn (auditoria $record): bool => $record->estado === EstadoAuditoria::PendienteAuditoria)
                    ->url(fn (auditoria $record): string => route('auditor.auditorias.revisar', $record)),

                Action::make('ver_evidencias')
                    ->label('Ver resultado')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->visible(fn (auditoria $record): bool => $record->estado !== EstadoAuditoria::PendienteAuditoria)
                    ->url(fn (auditoria $record): string => route('auditor.auditorias.revisar', $record)),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('estado')
                    ->label('Estado')
                    ->options(
                        collect(EstadoAuditoria::cases())
                            ->mapWithKeys(fn (EstadoAuditoria $case) => [$case->value => $case->label()])
                    ),
            ])
            ->emptyStateHeading('Sin auditorías registradas')
            ->emptyStateIcon('heroicon-o-clipboard-document-check')
            ->defaultSort('created_at', 'desc');
    }

    public function render(): View
    {
        return view('livewire.auditor.auditorias-index');
    }
}
