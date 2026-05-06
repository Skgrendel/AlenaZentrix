<?php

namespace App\Livewire\Auditor;

use App\Enums\EstadoAuditoria;
use App\Models\auditoria;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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
                    ->modalHeading('Registrar Auditoría')
                    ->modalSubmitActionLabel('Guardar auditoría')
                    ->visible(fn (auditoria $record): bool => $record->estado === EstadoAuditoria::PendienteAuditoria)
                    ->form([
                        FileUpload::make('evidencias')
                            ->label('Evidencias (PDFs / Imágenes)')
                            ->required()
                            ->multiple()
                            ->disk('s3')
                            ->directory('auditorias')
                            ->acceptedFileTypes(['image/*', 'application/pdf'])
                            ->maxFiles(10),
                        Select::make('estado')
                            ->label('Resultado')
                            ->options([
                                EstadoAuditoria::Confirmado->value => EstadoAuditoria::Confirmado->label(),
                                EstadoAuditoria::Negativo->value => EstadoAuditoria::Negativo->label(),
                            ])
                            ->required(),
                        Textarea::make('observaciones')
                            ->label('Observaciones')
                            ->rows(3),
                    ])
                    ->action(function (array $data, auditoria $record): void {
                        $record->update([
                            'evidencias' => $data['evidencias'],
                            'estado' => $data['estado'],
                            'observaciones' => $data['observaciones'] ?? null,
                            'auditado_por_id' => auth()->id(),
                            'fecha_auditoria' => now(),
                        ]);
                    }),

                Action::make('ver_evidencias')
                    ->label('Ver')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->visible(fn (auditoria $record): bool => $record->estado !== EstadoAuditoria::PendienteAuditoria)
                    ->url(fn (auditoria $record): string => route('operaciones.ordenes-trabajo.show', $record->reporte?->ordenJob)),
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
