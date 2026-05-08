<?php

namespace App\Livewire\Auditor;

use App\Enums\EstadoAuditoria;
use App\Models\auditoria;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Revisar Auditoría')]
/**
 * Pantalla de revisión de auditoría donde el auditor sube evidencias y registra el resultado
 * (Confirmado o Negativo) de una orden de Fraudes completada.
 */
class AuditoriaRevisar extends Component implements HasForms
{
    use InteractsWithForms;

    public auditoria $auditoria;

    /** Origen de navegación para saber a dónde redirigir al finalizar ('auditorias' u 'ordenes'). */
    public string $from = 'auditorias';

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(auditoria $auditoria): void
    {
        $from = request()->query('from', 'auditorias');
        $this->from = in_array($from, ['auditorias', 'ordenes']) ? $from : 'auditorias';
        abort_unless(in_array($auditoria->estado, [
            EstadoAuditoria::PendienteAuditoria,
            EstadoAuditoria::Confirmado,
            EstadoAuditoria::Negativo,
        ]), 403);

        $this->auditoria = $auditoria->load([
            'reporte.ordenJob.personal',
            'reporte.comercio.tipoComercio',
            'reporte.tipoImposibilidad',
            'reporte.tipoRegulador',
            'reporte.marcaMedidor',
            'reporte.marcaRegulador',
            'reporte.ubicacion',
            'auditadoPor.personal',
        ]);

        $this->form->fill();
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Registrar Auditoría')
                    ->schema([
                        Forms\Components\FileUpload::make('evidencias')
                            ->label('Evidencias (PDFs / Imágenes)')
                            ->required()
                            ->multiple()
                            ->disk('s3')
                            ->directory('auditorias')
                            ->acceptedFileTypes(['image/*', 'application/pdf'])
                            ->maxFiles(10),
                        Forms\Components\Select::make('resultado')
                            ->label('Resultado')
                            ->options([
                                EstadoAuditoria::Confirmado->value => EstadoAuditoria::Confirmado->label(),
                                EstadoAuditoria::Negativo->value => EstadoAuditoria::Negativo->label(),
                            ])
                            ->required(),
                        Forms\Components\Textarea::make('observaciones')
                            ->label('Observaciones')
                            ->rows(4),
                    ]),
            ])
            ->statePath('data');
    }

    /** Persiste el resultado de la auditoría y redirige al listado de origen. */
    public function guardar(): void
    {
        $data = $this->form->getState();

        $this->auditoria->update([
            'evidencias' => $data['evidencias'],
            'estado' => $data['resultado'],
            'observaciones' => $data['observaciones'] ?? null,
            'auditado_por_id' => auth()->id(),
            'fecha_auditoria' => now(),
        ]);

        session()->flash('success', "Auditoría de la orden {$this->auditoria->reporte?->ordenJob?->numero_orden} registrada correctamente.");

        $this->redirect($this->volverUrl(), navigate: true);
    }

    public function volverUrl(): string
    {
        return match ($this->from) {
            'ordenes' => route('operaciones.ordenes-trabajo'),
            default => route('auditor.auditorias'),
        };
    }

    /**
     * @return array<int, string>
     */
    public function imagenUrls(): array
    {
        return collect($this->auditoria->reporte?->imagenes ?? [])
            ->map(fn (string $path) => route('operaciones.foto', ['path' => $path]))
            ->all();
    }

    public function render(): View
    {
        return view('livewire.auditor.auditoria-revisar', [
            'reporte' => $this->auditoria->reporte,
            'orden' => $this->auditoria->reporte?->ordenJob,
            'imagenUrls' => $this->imagenUrls(),
        ]);
    }
}
