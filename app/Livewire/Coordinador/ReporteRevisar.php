<?php

namespace App\Livewire\Coordinador;

use App\Enums\EstadoOrden;
use App\Mail\OrdenCompletada;
use App\Mail\OrdenRechazada;
use App\Models\OrdenJob;
use App\Models\reportes;
use Illuminate\Contracts\View\View;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Revisar Reporte')]
class ReporteRevisar extends Component
{
    public OrdenJob $orden;

    public reportes $reporte;

    public string $comentarios = '';

    public bool $mostrarFormRechazo = false;

    public function mount(OrdenJob $orden): void
    {
        abort_unless($orden->estado === EstadoOrden::EnRevision, 403);

        $this->orden = $orden->load('personal');
        $this->reporte = $orden->reportes()
            ->with(['ubicacion', 'comercio.tipoComercio', 'tipoImposibilidad', 'tipoRegulador', 'marcaMedidor', 'marcaRegulador'])
            ->latest()
            ->firstOrFail();
    }

    public function completar(): void
    {
        $this->orden->update(['estado' => EstadoOrden::Completada]);

        $this->notificarTecnico(new OrdenCompletada($this->orden, $this->orden->personal));

        session()->flash('success', "Orden {$this->orden->numero_orden} marcada como completada.");

        $this->redirect(route('operaciones.ordenes-trabajo'), navigate: true);
    }

    public function rechazar(): void
    {
        $this->validate([
            'comentarios' => ['required', 'string', 'min:10'],
        ], [
            'comentarios.required' => 'Debe ingresar el motivo del rechazo.',
            'comentarios.min' => 'El motivo debe tener al menos 10 caracteres.',
        ]);

        $this->reporte->update(['comentarios' => $this->comentarios]);
        $this->orden->update(['estado' => EstadoOrden::Rechazada]);

        $this->notificarTecnico(new OrdenRechazada($this->orden, $this->orden->personal, $this->comentarios));

        session()->flash('success', "Orden {$this->orden->numero_orden} rechazada. El técnico podrá corregir y reenviar el reporte.");

        $this->redirect(route('operaciones.ordenes-trabajo'), navigate: true);
    }

    protected function notificarTecnico(Mailable $mail): void
    {
        $email = $this->orden->personal?->usuario?->email;

        if (! $email) {
            return;
        }

        try {
            Mail::to($email)->send($mail);
        } catch (\Exception $e) {
            Log::error('No se pudo enviar notificación al técnico.', [
                'orden' => $this->orden->numero_orden,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function imagenUrls(): array
    {
        return collect($this->reporte->imagenes ?? [])
            ->map(fn (string $path) => route('operaciones.foto', ['path' => $path]))
            ->all();
    }

    public function render(): View
    {
        return view('livewire.coordinador.reporte-revisar', [
            'imagenUrls' => $this->imagenUrls(),
        ]);
    }
}
