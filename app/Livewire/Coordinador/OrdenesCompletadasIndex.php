<?php

namespace App\Livewire\Coordinador;

use App\Enums\EstadoOrden;
use App\Enums\PrioridadOrden;
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
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Órdenes Completadas')]
class OrdenesCompletadasIndex extends Component implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                OrdenJob::query()
                    ->with(['personal', 'reportes' => fn ($q) => $q->with(['comercio.tipoComercio', 'tipoImposibilidad', 'tipoRegulador', 'marcaMedidor', 'marcaRegulador'])->latest()])
                    ->where('estado', EstadoOrden::Completada)
            )
            ->columns([
                Tables\Columns\TextColumn::make('numero_orden')
                    ->label('N° Orden')
                    ->badge()
                    ->color('success')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('cliente')
                    ->label('Cliente')
                    ->searchable(),
                Tables\Columns\TextColumn::make('contrato')
                    ->label('Contrato')
                    ->searchable(),
                Tables\Columns\TextColumn::make('barrio')
                    ->label('Barrio'),
                Tables\Columns\TextColumn::make('personal.nombres')
                    ->label('Técnico')
                    ->formatStateUsing(fn ($state, OrdenJob $record): string => $record->personal
                        ? "{$record->personal->nombres} {$record->personal->apellidos}"
                        : '—'
                    ),
                Tables\Columns\TextColumn::make('reporte_lectura')
                    ->label('Lectura')
                    ->getStateUsing(fn (OrdenJob $record): string => $record->reportes->first()?->lectura ?? '—'),
                Tables\Columns\TextColumn::make('reporte_medidor')
                    ->label('N° Medidor')
                    ->getStateUsing(fn (OrdenJob $record): string => $record->reportes->first()?->numero_medidor ?? '—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('reporte_comercio')
                    ->label('Comercio')
                    ->getStateUsing(fn (OrdenJob $record): string => $record->reportes->first()?->comercio?->nombre_comercio ?? '—'),
                Tables\Columns\TextColumn::make('reporte_tipo_comercio')
                    ->label('Tipo Comercio')
                    ->getStateUsing(fn (OrdenJob $record): string => $record->reportes->first()?->comercio?->tipoComercio?->nombre ?? '—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('reporte_imposibilidad')
                    ->label('Imposibilidad')
                    ->getStateUsing(fn (OrdenJob $record): string => $record->reportes->first()?->tipoImposibilidad?->nombre ?? '—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('reporte_regulador')
                    ->label('Regulador')
                    ->getStateUsing(fn (OrdenJob $record): string => $record->reportes->first()?->tipoRegulador?->nombre ?? '—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('reporte_cau')
                    ->label('CAU')
                    ->getStateUsing(fn (OrdenJob $record): string => $record->reportes->first()?->cau ?? '—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('reporte_observaciones')
                    ->label('Observaciones')
                    ->getStateUsing(fn (OrdenJob $record): string => $record->reportes->first()?->observaciones ?? '—')
                    ->limit(60)
                    ->tooltip(fn (OrdenJob $record): ?string => $record->reportes->first()?->observaciones)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('fecha_completado')
                    ->label('Fecha Completado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->headerActions([
                Action::make('exportar')
                    ->label('Exportar CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->url(route('operaciones.exportar-completadas'))
                    ->openUrlInNewTab(),
            ])
            ->actions([
                Action::make('view')
                    ->label('Ver')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn (OrdenJob $record): string => route('operaciones.ordenes-trabajo.show', $record)),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('prioridad')
                    ->label('Prioridad')
                    ->options(
                        collect(PrioridadOrden::cases())
                            ->mapWithKeys(fn (PrioridadOrden $case) => [$case->value => $case->label()])
                    ),
            ])
            ->emptyStateHeading('Sin órdenes completadas')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->defaultSort('fecha_completado', 'desc');
    }

    public function render(): View
    {
        return view('livewire.coordinador.ordenes-completadas-index');
    }
}
