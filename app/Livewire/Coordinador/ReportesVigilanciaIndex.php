<?php

namespace App\Livewire\Coordinador;

use App\Enums\EstadoOrden;
use App\Enums\TipoServicio;
use App\Mail\OrdenCompletada;
use App\Mail\OrdenRechazada;
use App\Models\OrdenJob;
use App\Models\reportes;
use App\Models\ResultadoReporte;
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
use Illuminate\Mail\Mailable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Reportes de Vigilancia')]
class ReportesVigilanciaIndex extends Component implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    public OrdenJob $orden;

    public string $comentarios = '';

    public bool $mostrarFormRechazo = false;

    public function mount(OrdenJob $orden): void
    {
        abort_unless($orden->tipo_servicio === TipoServicio::Vigilancia, 403);
        abort_unless(
            in_array($orden->estado, [EstadoOrden::EnRevision, EstadoOrden::Completada, EstadoOrden::EnProgreso]),
            403
        );

        $this->orden = $orden->load('personal');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(reportes::query()->where('orden_job_id', $this->orden->id)->with('personal'))
            ->columns([
                Tables\Columns\TextColumn::make('turno')
                    ->label('N°')
                    ->state(fn (reportes $r, $rowLoop) => $rowLoop->iteration)
                    ->alignCenter()
                    ->width('50px'),
                Tables\Columns\TextColumn::make('fecha_turno')
                    ->label('Fecha del Turno')
                    ->state(function (reportes $r): string {
                        $fecha = $r->datos_servicio['fecha_turno'] ?? null;

                        return $fecha
                            ? Carbon::parse($fecha)->format('d/m/Y')
                            : '—';
                    }),
                Tables\Columns\TextColumn::make('horario')
                    ->label('Horario')
                    ->state(function (reportes $r): string {
                        $inicio = $r->datos_servicio['hora_inicio'] ?? null;
                        $fin = $r->datos_servicio['hora_fin'] ?? null;

                        return ($inicio && $fin) ? "{$inicio} – {$fin}" : '—';
                    }),
                Tables\Columns\IconColumn::make('hay_novedad')
                    ->label('Novedades')
                    ->state(fn (reportes $r): bool => (bool) ($r->datos_servicio['hay_novedad'] ?? false))
                    ->boolean()
                    ->trueColor('warning')
                    ->falseColor('success'),
                Tables\Columns\IconColumn::make('hay_reincidencia')
                    ->label('Reincidencia')
                    ->state(fn (reportes $r): bool => (bool) ($r->datos_servicio['hay_reincidencia'] ?? false))
                    ->boolean()
                    ->trueColor('danger')
                    ->falseColor('gray'),
                Tables\Columns\TextColumn::make('resultado')
                    ->label('Resultado')
                    ->state(function (reportes $r): string {
                        $id = $r->datos_servicio['resultado_reporte_id'] ?? null;

                        return $id ? (ResultadoReporte::find($id)?->nombre ?? '—') : '—';
                    })
                    ->badge()
                    ->color('blue'),
                Tables\Columns\TextColumn::make('personal.nombres')
                    ->label('Registrado por')
                    ->state(fn (reportes $r): string => $r->personal
                        ? "{$r->personal->nombres} {$r->personal->apellidos}"
                        : '—'
                    ),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha Registro')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'asc')
            ->actions([
                Action::make('ver')
                    ->label('Ver Reporte')
                    ->icon('heroicon-o-eye')
                    ->color('primary')
                    ->url(fn (reportes $r): string => route('operaciones.ordenes-trabajo.revisar', $this->orden).'?reporte='.$r->id),
            ])
            ->emptyStateHeading('Sin reportes registrados')
            ->emptyStateDescription('Los investigadores aún no han registrado turnos para esta orden.')
            ->emptyStateIcon('heroicon-o-document-text');
    }

    public function completarAction(): Action
    {
        return Action::make('completar')
            ->label('Marcar orden como Completada')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Confirmar completar orden')
            ->modalDescription("¿Confirmas que los reportes de vigilancia de la orden {$this->orden->numero_orden} son correctos?")
            ->modalSubmitActionLabel('Sí, completar')
            ->extraAttributes(['class' => 'w-full justify-center'])
            ->action(function (): void {
                $this->orden->update(['estado' => EstadoOrden::Completada]);

                $this->notificarPersonal(new OrdenCompletada($this->orden, $this->orden->personal));

                session()->flash('success', "Orden {$this->orden->numero_orden} completada.");

                $this->redirect(route('operaciones.ordenes-trabajo'), navigate: true);
            });
    }

    public function rechazar(): void
    {
        $this->validate([
            'comentarios' => ['required', 'string', 'min:10'],
        ], [
            'comentarios.required' => 'Debe ingresar el motivo del rechazo.',
            'comentarios.min' => 'El motivo debe tener al menos 10 caracteres.',
        ]);

        $this->orden->update(['estado' => EstadoOrden::Rechazada]);

        $this->notificarPersonal(new OrdenRechazada($this->orden, $this->orden->personal, $this->comentarios));

        session()->flash('success', "Orden {$this->orden->numero_orden} rechazada.");

        $this->redirect(route('operaciones.ordenes-trabajo'), navigate: true);
    }

    protected function notificarPersonal(Mailable $mail): void
    {
        $email = $this->orden->personal?->usuario?->email;

        if (! $email) {
            return;
        }

        try {
            Mail::to($email)->send($mail);
        } catch (\Exception $e) {
            Log::error('No se pudo notificar al investigador sobre la orden de vigilancia.', [
                'orden' => $this->orden->numero_orden,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function render(): View
    {
        return view('livewire.coordinador.reportes-vigilancia-index');
    }
}
