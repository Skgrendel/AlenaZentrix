<?php

namespace App\Livewire\Mapa;

use App\Models\personals;
use App\Models\reportes;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Mapa de Ubicaciones')]
class MapaIndex extends Component
{
    public string $fechaDesde;

    public string $fechaHasta;

    public ?string $tecnicoId = null;

    public ?string $estadoReporte = null;

    public ?string $ordenTrabajo = null;

    public function mount(): void
    {
        $this->fechaDesde = now()->subDays(30)->toDateString();
        $this->fechaHasta = now()->toDateString();
    }

    /** @return array<string, string> */
    #[Computed]
    public function tecnicos(): array
    {
        return personals::query()
            ->select(['id', 'nombres', 'apellidos'])
            ->orderBy('nombres')
            ->get()
            ->mapWithKeys(fn (personals $p) => [$p->id => "{$p->nombres} {$p->apellidos}"])
            ->all();
    }

    /**
     * @return array<int, array{
     *   lat: float,
     *   lng: float,
     *   tecnico: string,
     *   fecha: string,
     *   numero_orden: string,
     *   estado: string,
     *   direccion: string,
     *   tipo_servicio: string,
     *   anomalias: array<int, mixed>,
     *   reporte_url: string
     * }>
     */
    #[Computed]
    public function marcadores(): array
    {
        return reportes::with(['ubicacion', 'personal', 'ordenJob'])
            ->whereHas('ubicacion', fn ($q) => $q->whereNotNull('latitud')->whereNotNull('longitud'))
            ->whereBetween('created_at', [
                $this->fechaDesde.' 00:00:00',
                $this->fechaHasta.' 23:59:59',
            ])
            ->when($this->tecnicoId, fn ($q) => $q->where('personals_id', $this->tecnicoId))
            ->when($this->estadoReporte, fn ($q) => $q->where('estado', $this->estadoReporte))
            ->when($this->ordenTrabajo, fn ($q) => $q->where('numero_orden', 'like', "%{$this->ordenTrabajo}%"))
            ->get()
            ->map(fn (reportes $reporte) => [
                'lat' => (float) $reporte->ubicacion->latitud,
                'lng' => (float) $reporte->ubicacion->longitud,
                'tecnico' => $reporte->personal
                    ? "{$reporte->personal->nombres} {$reporte->personal->apellidos}"
                    : '—',
                'fecha' => $reporte->created_at?->format('d/m/Y') ?? '—',
                'numero_orden' => $reporte->numero_orden,
                'estado' => $reporte->estado,
                'direccion' => $reporte->ubicacion->direccion ?? '—',
                'tipo_servicio' => $reporte->ordenJob?->tipo_servicio?->label() ?? '—',
                'anomalias' => is_array($reporte->anomalia) ? $reporte->anomalia : [],
                'reporte_url' => route('operaciones.ordenes-trabajo.show', $reporte->orden_job_id),
            ])
            ->values()
            ->all();
    }

    public function updated(): void
    {
        $this->dispatch('markers-updated', marcadores: $this->marcadores);
    }

    public function limpiarFiltros(): void
    {
        $this->fechaDesde = now()->subDays(30)->toDateString();
        $this->fechaHasta = now()->toDateString();
        $this->tecnicoId = null;
        $this->estadoReporte = null;
        $this->ordenTrabajo = null;

        $this->dispatch('markers-updated', marcadores: $this->marcadores);
    }

    public function render(): View
    {
        return view('livewire.mapa.mapa-index', [
            'marcadores' => $this->marcadores,
            'tecnicos' => $this->tecnicos,
        ]);
    }
}
