<?php

namespace App\Livewire\Coordinador;

use App\Enums\EstadoOrden;
use App\Enums\TipoServicio;
use App\Mail\OrdenCompletada;
use App\Mail\OrdenRechazada;
use App\Models\Convencion;
use App\Models\OrdenJob;
use App\Models\reportes;
use App\Models\ResultadoReporte;
use App\Models\TipoComercio;
use App\Models\TipologiaOrden;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Illuminate\Contracts\View\View;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Revisar Reporte')]
/**
 * Pantalla de revisión de reporte para el coordinador: permite aprobar (completar) o rechazar
 * la orden después de que el técnico envió su reporte.
 */
class ReporteRevisar extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    public OrdenJob $orden;

    public reportes $reporte;

    public string $comentarios = '';

    public string $conclusion = '';

    public bool $mostrarFormRechazo = false;

    public function mount(OrdenJob $orden): void
    {
        $estadosPermitidos = [EstadoOrden::EnRevision, EstadoOrden::Completada];

        // Vigilancia permite revisar reportes individuales mientras la orden sigue EnProgreso
        if ($orden->tipo_servicio === TipoServicio::Vigilancia) {
            $estadosPermitidos[] = EstadoOrden::EnProgreso;
        }

        abort_unless(in_array($orden->estado, $estadosPermitidos), 403);

        $this->orden = $orden->load('personal');

        $reporteId = request()->query('reporte');

        $query = $orden->reportes()
            ->with(['ubicacion', 'comercio.tipoComercio', 'tipoImposibilidad', 'tipoRegulador', 'marcaMedidor', 'marcaRegulador', 'personal']);

        $this->reporte = $reporteId
            ? $query->where('id', $reporteId)->firstOrFail()
            : $query->latest()->firstOrFail();
    }

    public function completar(): void
    {
        $this->validate([
            'conclusion' => ['required', 'string', 'min:10'],
        ], [
            'conclusion.required' => 'Debe ingresar una conclusión.',
            'conclusion.min' => 'La conclusión debe tener al menos 10 caracteres.',
        ]);

        $this->reporte->update(['conclusion' => $this->conclusion]);
        $this->orden->update(['estado' => EstadoOrden::Completada]);

        $this->notificarTecnico(new OrdenCompletada($this->orden, $this->orden->personal));

        session()->flash('success', "Orden {$this->orden->numero_orden} marcada como completada.");

        $this->redirect(route('operaciones.ordenes-trabajo'), navigate: true);
    }

    /**
     * Rechaza la orden con el motivo indicado. El técnico podrá corregir y reenviar el reporte.
     * Los comentarios se persisten en el reporte para que el técnico conozca el motivo.
     */
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

    /**
     * @return array<int, string>
     */
    public function imagenUrls(): array
    {
        return collect($this->reporte->imagenes ?? [])
            ->map(fn (string $path) => route('operaciones.foto', ['path' => $path]))
            ->all();
    }

    /**
     * @return array<int, array{url: string, nombre: string, es_imagen: bool}>
     */
    public function archivosUrls(): array
    {
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'tiff', 'tif'];

        return collect($this->reporte->imagenes ?? [])
            ->map(function (string $path) use ($imageExtensions): array {
                $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

                return [
                    'url' => route('operaciones.foto', ['path' => $path]),
                    'nombre' => basename($path),
                    'es_imagen' => in_array($extension, $imageExtensions, true),
                ];
            })
            ->all();
    }

    /**
     * Normaliza `datos_servicio` (JSON) a un mapa legible por la vista según el tipo de servicio.
     * Resuelve IDs de catálogos a sus nombres para evitar consultas en Blade.
     *
     * @return array<string, mixed>
     */
    public function datosServicioDisplay(): array
    {
        $datos = $this->reporte->datos_servicio ?? [];
        $tipo = $this->orden->tipo_servicio;

        if ($tipo === TipoServicio::Investigacion) {
            return [
                'convencion' => Convencion::find($datos['convencion_id'] ?? null)?->nombre,
                'acompanamiento_tecnico' => $datos['acompanamiento_tecnico'] ?? false,
                'acompanante_nombre' => $datos['acompanante_nombre'] ?? null,
                'acompanante_identificacion' => $datos['acompanante_identificacion'] ?? null,
                'acompanante_cargo' => $datos['acompanante_cargo'] ?? null,
                'acompanante_cuadrilla' => $datos['acompanante_cuadrilla'] ?? null,
                'tipo_investigado' => $datos['tipo_investigado'] ?? null,
                'nombre_investigado' => $datos['nombre_investigado'] ?? null,
                'identificacion' => $datos['identificacion'] ?? null,
                'descripcion_fisica' => $datos['descripcion_fisica'] ?? null,
                'nombre_comercio_inv' => $datos['nombre_comercio_inv'] ?? null,
                'tipo_comercio_inv' => TipoComercio::find($datos['tipo_comercio_inv_id'] ?? null)?->nombre,
                'resultado' => ResultadoReporte::find($datos['resultado_reporte_id'] ?? null)?->nombre,
                'actividades' => $datos['actividades'] ?? null,
            ];
        }

        if ($tipo === TipoServicio::GestionLegal) {
            $subtipos = [
                'capturas' => 'Capturas Criminales',
                'bandas' => 'Desarticulación de Bandas',
                'querella' => 'Querella Penal',
                'seguimiento_denuncia' => 'Seguimiento a Denuncia',
                'diligencia_judicial' => 'Diligencia Judicial',
            ];

            return [
                'tipo_gestion_label' => $subtipos[$this->orden->tipo_gestion_legal ?? ''] ?? ($this->orden->tipo_gestion_legal ?? '—'),
                'numero_noticia_criminal' => $this->orden->numero_noticia_criminal,
                'tipologia' => TipologiaOrden::find($datos['tipologia_orden_id'] ?? null)?->nombre,
                'fecha_ejecucion' => $datos['fecha_ejecucion'] ?? null,
                'departamento' => $datos['departamento'] ?? null,
                'municipio' => $datos['municipio'] ?? null,
                'radio_objetivo' => $datos['radio_objetivo'] ?? null,
                'actividades' => $datos['actividades'] ?? null,
                'resultado' => ResultadoReporte::find($datos['resultado_reporte_id'] ?? null)?->nombre,
            ];
        }

        if ($tipo === TipoServicio::Vigilancia) {
            return [
                'fecha_turno' => $datos['fecha_turno'] ?? null,
                'hora_inicio' => $datos['hora_inicio'] ?? null,
                'hora_fin' => $datos['hora_fin'] ?? null,
                'hay_novedad' => $datos['hay_novedad'] ?? false,
                'descripcion_novedad' => $datos['descripcion_novedad'] ?? null,
                'hay_reincidencia' => $datos['hay_reincidencia'] ?? false,
                'actividades' => $datos['actividades'] ?? null,
                'resultado' => ResultadoReporte::find($datos['resultado_reporte_id'] ?? null)?->nombre,
            ];
        }

        return [];
    }

    public function render(): View
    {
        // Recargar la orden para obtener datos más recientes (como actividades)
        $this->orden->refresh();

        return view('livewire.coordinador.reporte-revisar', [
            'imagenUrls' => $this->imagenUrls(),
            'datosServicio' => $this->datosServicioDisplay(),
            'archivosUrls' => $this->archivosUrls(),
        ]);
    }
}
