<?php

namespace App\Livewire\Coordinador\SurveyAuditoria;

use App\Enums\EstadoSurveyRegistro;
use App\Exports\SurveyAuditoria\SurveyAuditoriaExport;
use App\Models\personals;
use App\Models\SurveyRegistro;
use App\Services\coordinador\AsignarAuditoriaSurveyService;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

#[Layout('layouts.app')]
#[Title('Auditoría de Suspensiones')]
/**
 * Registros de suspensiones reportados en Survey123, con la opción de asignarlos
 * a un auditor de campo (orden de Visita de Calidad — Suspensiones).
 */
class SurveyRegistrosIndex extends Component implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        return $table
            ->query(SurveyRegistro::query()->with('ordenes'))
            ->columns([
                Tables\Columns\TextColumn::make('orden_trabajo')
                    ->label('OT')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary')
                    ->url(fn (SurveyRegistro $record): string => route('operaciones.survey-auditoria.show', $record)),
                Tables\Columns\TextColumn::make('contrato')
                    ->label('Contrato')
                    ->searchable(),
                Tables\Columns\TextColumn::make('tecnico')
                    ->label('Técnico')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('fecha_registro')
                    ->label('Fecha de registro')
                    ->dateTime('d/m/Y h:i a')
                    ->sortable(),
                Tables\Columns\TextColumn::make('categoria')
                    ->label('Categoría')
                    ->formatStateUsing(fn (?string $state): string => $state ? ucfirst($state) : '—'),
                Tables\Columns\IconColumn::make('suspension_ejecutada')
                    ->label('Ejecutada')
                    ->boolean(),
                Tables\Columns\TextColumn::make('metodo_suspension')
                    ->label('Método')
                    ->formatStateUsing(fn (?string $state): string => self::METODOS[$state] ?? '—'),
                Tables\Columns\TextColumn::make('serie_medidor')
                    ->label('Serie medidor')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('lectura_medidor')
                    ->label('Lectura')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (EstadoSurveyRegistro $state): string => $state->label())
                    ->color(fn (EstadoSurveyRegistro $state): string => $state->color()),
                Tables\Columns\TextColumn::make('total_errores')
                    ->label('Errores ✖')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_alertas')
                    ->label('Alertas ⚠')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'warning' : 'gray')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('con_errores')
                    ->label('Con errores')
                    ->queries(
                        true: fn ($query) => $query->where('total_errores', '>', 0),
                        false: fn ($query) => $query->where('total_errores', 0),
                    ),
                Tables\Filters\SelectFilter::make('estado')
                    ->label('Estado')
                    ->options(
                        collect(EstadoSurveyRegistro::cases())
                            ->mapWithKeys(fn (EstadoSurveyRegistro $case) => [$case->value => $case->label()])
                    ),
            ])
            ->headerActions([
                Action::make('exportar_excel')
                    ->label('Exportar Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->visible(fn (): bool => $this->puedeExportar())
                    ->action(fn (): BinaryFileResponse => $this->exportar($this->getFilteredTableQuery())),
            ])
            ->actions([
                Action::make('ver_detalle')
                    ->label('Ver detalle')
                    ->icon('heroicon-o-photo')
                    ->color('gray')
                    ->url(fn (SurveyRegistro $record): string => route('operaciones.survey-auditoria.show', $record)),
                Action::make('hallazgos')
                    ->label('Hallazgos')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('gray')
                    ->modalHeading(fn (SurveyRegistro $record): string => "Hallazgos de la auditoría: OT {$record->orden_trabajo}")
                    ->modalContent(fn (SurveyRegistro $record) => view('livewire.coordinador.survey-auditoria.hallazgos', ['registro' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar'),
                Action::make('asignar')
                    ->label('Asignar')
                    ->icon('heroicon-o-user-plus')
                    ->color('primary')
                    ->modalHeading(fn (SurveyRegistro $record): string => "Asignar auditoría de campo: OT {$record->orden_trabajo}")
                    ->modalSubmitActionLabel('Asignar')
                    ->visible(fn (SurveyRegistro $record): bool => $this->puedeAsignar()
                        && $record->estado === EstadoSurveyRegistro::Pendiente)
                    ->form($this->formularioAsignacion())
                    ->action(function (SurveyRegistro $record, array $data): void {
                        $this->asignarRegistros(collect([$record]), $data);
                    }),
                Action::make('ver_orden')
                    ->label('Ver orden')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->visible(fn (SurveyRegistro $record): bool => $record->ordenes->isNotEmpty())
                    ->url(fn (SurveyRegistro $record): string => route('operaciones.ordenes-trabajo.show', $record->ordenes->sortByDesc('created_at')->first())),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('exportar_seleccionados')
                        ->label('Exportar seleccionados')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->visible(fn (): bool => $this->puedeExportar())
                        ->action(fn (Collection $records): BinaryFileResponse => $this->exportar(
                            SurveyRegistro::query()->whereIn('survey_registros.id', $records->modelKeys())
                        )),
                    BulkAction::make('asignar_seleccionados')
                        ->label('Asignar seleccionados')
                        ->icon('heroicon-o-user-plus')
                        ->modalHeading('Asignar auditoría de campo a los registros seleccionados')
                        ->modalSubmitActionLabel('Asignar')
                        ->visible(fn (): bool => $this->puedeAsignar())
                        ->form($this->formularioAsignacion())
                        ->action(function (Collection $records, array $data): void {
                            $this->asignarRegistros($records, $data);
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->emptyStateHeading('Sin registros de suspensiones')
            ->emptyStateDescription('Aún no hay registros de Survey123 para auditar.')
            ->emptyStateIcon('heroicon-o-clipboard-document-check')
            ->defaultSort('fecha_registro', 'desc');
    }

    /** @var array<string, string> */
    private const METODOS = [
        'sonda_ips' => 'Sonda (Dispositivo IPS)',
        'sonda_cts' => 'Sonda (Dispositivo CTS)',
        'taponamiento_acometida' => 'Taponamiento de acometida',
        'taponamiento_multifamiliar' => 'Taponamiento Multifamiliar',
    ];

    private function puedeAsignar(): bool
    {
        return auth()->user()?->can('ordenes.crear') ?? false;
    }

    private function puedeExportar(): bool
    {
        return auth()->user()?->can('ordenes.ver') ?? false;
    }

    /**
     * Descarga el libro con los registros de la consulta, siempre acotados a la empresa del usuario.
     *
     * @param  Builder<SurveyRegistro>  $registros
     */
    private function exportar($registros): BinaryFileResponse
    {
        abort_unless($this->puedeExportar(), 403);

        $companyId = auth()->user()->company_id;
        abort_if($companyId === null, 403);

        $registros = $registros->withoutGlobalScopes()->where('survey_registros.company_id', $companyId);

        return Excel::download(new SurveyAuditoriaExport($registros), SurveyAuditoriaExport::nombreDeArchivo());
    }

    /** @return array<int, mixed> */
    private function formularioAsignacion(): array
    {
        return [
            Forms\Components\Select::make('personals_id')
                ->label('Auditor de campo')
                ->options(fn (): array => personals::query()
                    ->select(['id', 'nombres', 'apellidos'])
                    ->orderBy('nombres')
                    ->orderBy('apellidos')
                    ->get()
                    ->mapWithKeys(fn ($p) => [$p->id => "{$p->nombres} {$p->apellidos}"])
                    ->all())
                ->searchable()
                ->required(),
            Forms\Components\DateTimePicker::make('fecha_limite')
                ->label('Fecha límite de la visita')
                ->helperText('Por defecto, 48 horas desde la asignación.')
                ->default(fn () => now()->addHours(AsignarAuditoriaSurveyService::PLAZO_HORAS))
                ->required(),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, SurveyRegistro>  $registros
     * @param  array<string, mixed>  $data
     */
    private function asignarRegistros($registros, array $data): void
    {
        abort_unless($this->puedeAsignar(), 403);

        $servicio = app(AsignarAuditoriaSurveyService::class);
        $asignados = 0;
        $omitidos = [];

        foreach ($registros as $registro) {
            try {
                $servicio->asignar($registro, $data['personals_id'], Carbon::parse($data['fecha_limite']));
                $asignados++;
            } catch (DomainException $e) {
                $omitidos[] = "OT {$registro->orden_trabajo}: {$e->getMessage()}";
            }
        }

        if ($asignados > 0) {
            Notification::make()
                ->title($asignados === 1 ? 'Auditoría asignada' : "{$asignados} auditorías asignadas")
                ->success()
                ->send();
        }

        if ($omitidos !== []) {
            Notification::make()
                ->title('Algunos registros no se asignaron')
                ->body(implode("\n", $omitidos))
                ->warning()
                ->send();
        }
    }

    public function render(): View
    {
        return view('livewire.coordinador.survey-auditoria.survey-registros-index');
    }
}
