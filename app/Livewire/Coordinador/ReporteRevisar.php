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
use App\Support\ArchivoUrl;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
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

    public $resultado = ''; // En tu clase Livewire

    /** Valor diligenciado para completar `metros_cubicos_recuperados` después de registrada la auditoría. */
    public ?string $metrosCubicosRecuperados = null;

    /** Valor diligenciado para registrar la fecha en la que se entregó el informe/resultado al cliente. */
    public ?string $fechaEntrega = null;

    public function mount(OrdenJob $orden): void
    {
        $estadosPermitidos = [EstadoOrden::EnRevision, EstadoOrden::Completada];

        // Vigilancia permite revisar reportes individuales mientras la orden sigue EnProgreso
        if ($orden->tipo_servicio === TipoServicio::Vigilancia) {
            $estadosPermitidos[] = EstadoOrden::EnProgreso;
        }

        abort_unless(in_array($orden->estado, $estadosPermitidos), 403);

        $this->orden = $orden->load(['personal', 'proceso']);

        $reporteId = request()->query('reporte');

        $query = $orden->reportes()
            ->with([
                'ubicacion', 'comercio.tipoComercio', 'tipoImposibilidad', 'tipoRegulador', 'marcaMedidor', 'marcaRegulador', 'personal',
                'auditoria.tipoAnomalia', 'auditoria.auditadoPor.personal',
            ]);

        $this->reporte = $reporteId
            ? $query->where('id', $reporteId)->firstOrFail()
            : $query->latest()->firstOrFail();
    }

    public function completar(): void
    {
        $this->validate([
            'conclusion' => ['required', 'string', 'min:10'],
            'resultado' => ['required', 'string', Rule::in(['positivo', 'negativo'])],
        ], [
            'conclusion.required' => 'Debe ingresar una conclusión.',
            'conclusion.min' => 'La conclusión debe tener al menos 10 caracteres.',
            'resultado.required' => 'Debe seleccionar un resultado.',
            'resultado.in' => 'El resultado debe ser Positivo o Negativo.',
        ]);

        // Actualizar el reporte con la conclusión y resultado, y marcar la orden como completada
        $this->reporte->update(['conclusion' => $this->conclusion]);
        // Guardar el resultado seleccionado en el reporte
        $this->orden->update(['estado' => EstadoOrden::Completada, 'resultado' => $this->resultado]);
        // Enviar notificación al técnico con la conclusión y resultado
        $this->notificarTecnico(new OrdenCompletada($this->orden, $this->orden->personal));

        // Mostrar mensaje de éxito y redirigir al listado de órdenes
        session()->flash('success', "Orden {$this->orden->numero_orden} marcada como completada.");
        // Redirigir al listado de órdenes después de completar la revisión
        $this->redirect(route('operaciones.ordenes-trabajo'), navigate: true);
    }

    /**
     * Rechaza el reporte con el motivo indicado. En Vigilancia, el rechazo es puntual a este
     * turno: la orden permanece activa (no pasa a Rechazada) y el resto de turnos no se ve
     * afectado, para que el investigador pueda seguir agregando reportes mientras corrige este.
     * En el resto de servicios (un solo reporte por orden), rechazar bloquea toda la orden.
     */
    public function rechazar(): void
    {
        $this->validate([
            'comentarios' => ['required', 'string', 'min:10'],
        ], [
            'comentarios.required' => 'Debe ingresar el motivo del rechazo.',
            'comentarios.min' => 'El motivo debe tener al menos 10 caracteres.',
        ]);

        // Actualizar el reporte con los comentarios del rechazo y marcarlo como el que requiere
        // corrección, para que ReporteEdit sepa cuál cargar (una orden de Vigilancia puede tener
        // varios reportes; sin esta marca se cargaría el más reciente en vez del rechazado).
        $this->reporte->update([
            'comentarios' => $this->comentarios,
            'requiere_correccion' => true,
        ]);

        $this->notificarTecnico(new OrdenRechazada($this->orden, $this->orden->personal, $this->comentarios));

        if ($this->orden->tipo_servicio === TipoServicio::Vigilancia) {
            session()->flash('success', 'Turno marcado para corrección. El investigador podrá editarlo sin afectar el resto de la orden.');

            $this->redirect(route('operaciones.ordenes-trabajo.reportes', $this->orden), navigate: true);

            return;
        }

        // Servicios de un solo reporte: rechazar bloquea toda la orden hasta que se corrija.
        // sincronizado vuelve a false para que la app sepa que debe reenviar el reporte corregido.
        $this->orden->update(['estado' => EstadoOrden::Rechazada, 'sincronizado' => false]);

        session()->flash('success', "Orden {$this->orden->numero_orden} rechazada. El técnico podrá corregir y reenviar el reporte.");

        $this->redirect(route('operaciones.ordenes-trabajo'), navigate: true);
    }

    /** Completa `metros_cubicos_recuperados` en una auditoría ya registrada que quedó sin ese dato. */
    public function guardarMetrosCubicosRecuperados(): void
    {
        $this->validate([
            'metrosCubicosRecuperados' => ['required', 'numeric', 'min:0'],
        ]);

        $this->reporte->auditoria?->update([
            'metros_cubicos_recuperados' => $this->metrosCubicosRecuperados,
        ]);

        Flux::modal('agregar-metros-cubicos')->close();

        Flux::toast(variant: 'success', text: 'Metros cúbicos recuperados registrados correctamente.');
    }

    /** Registra la fecha en la que se entregó el informe/resultado de la orden al cliente. */
    public function guardarFechaEntrega(): void
    {
        $this->validate([
            'fechaEntrega' => ['required', 'date'],
        ]);

        $this->orden->update(['fecha_entrega' => $this->fechaEntrega]);

        Flux::modal('agregar-fecha-entrega')->close();

        Flux::toast(variant: 'success', text: 'Fecha de entrega registrada correctamente.');
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
            ->map(fn (string $path) => ArchivoUrl::temporal($path))
            ->all();
    }

    /**
     * @return array<int, array{url: string, nombre: string, es_imagen: bool}>
     */
    public function archivosUrls(): array
    {
        return $this->mapArchivosConUrl($this->reporte->imagenes ?? []);
    }

    /**
     * @return array<int, array{url: string, nombre: string, es_imagen: bool}>
     */
    public function auditoriaArchivosUrls(): array
    {
        return $this->mapArchivosConUrl($this->reporte->auditoria?->evidencias ?? []);
    }

    /**
     * @param  array<int, string>  $paths
     * @return array<int, array{url: string, nombre: string, es_imagen: bool}>
     */
    private function mapArchivosConUrl(array $paths): array
    {
        $imageExtensions = ['jpg', 'jpeg', 'jfif', 'png', 'gif', 'webp', 'bmp', 'svg', 'tiff', 'tif', 'heic', 'heif', 'avif'];

        return collect($paths)
            ->map(function (string $path) use ($imageExtensions): array {
                $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

                return [
                    'url' => ArchivoUrl::temporal($path),
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
            $tipologiaLabel = TipologiaOrden::find($datos['tipologia_orden_id'] ?? null)?->nombre;

            return [
                'tipo_gestion_label' => $tipologiaLabel ?? TipologiaOrden::find($this->orden->tipo_gestion_legal ?? null)?->nombre ?? '—',
                'numero_noticia_criminal' => $this->orden->numero_noticia_criminal,
                'tipologia' => $tipologiaLabel,
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

        if ($tipo === TipoServicio::AuditoriaCampo) {
            $subtipo = match ($this->orden->tipo_auditoria_campo) {
                'visita_calidad_emergencia' => 'Visita de Calidad — Emergencia',
                'visita_calidad_trabajos_varios' => 'Visita de Calidad — Trabajos Varios',
                default => 'Auditoría de Campo',
            };

            return [
                'subtipo' => $subtipo,
                'fecha_hora_atencion' => $datos['fecha_hora_atencion'] ?? null,
                'ciudad' => $datos['ciudad'] ?? null,
                'celular_usuario' => $datos['celular_usuario'] ?? null,
                'comentario_usuario' => $datos['comentario_usuario'] ?? null,
                'comentario_tecnico' => $datos['comentario_tecnico'] ?? null,
                'nombre_atiende' => $datos['nombre_atiende'] ?? null,
                'vinculo_usuario' => $datos['vinculo_usuario'] ?? null,
                'celular_atiende' => $datos['celular_atiende'] ?? null,
                'recibio_visita' => $datos['recibio_visita'] ?? false,
                'se_realizo_revision' => $datos['se_realizo_revision'] ?? false,
                'fecha_ejecucion' => $datos['fecha_ejecucion'] ?? null,
                'tiempo_ejecucion' => $datos['tiempo_ejecucion'] ?? null,
                'descripcion_trabajo' => $datos['descripcion_trabajo'] ?? null,
                'se_detecto_fuga' => $datos['se_detecto_fuga'] ?? false,
                'ppm' => $datos['ppm'] ?? null,
                'vol_porcentaje' => $datos['vol_porcentaje'] ?? null,
                'problema_persiste' => $datos['problema_persiste'] ?? false,
                'evidencia_reparacion' => $datos['evidencia_reparacion'] ?? false,
                'servicio_correcto' => $datos['servicio_correcto'] ?? false,
                'se_usaron_materiales' => $datos['se_usaron_materiales'] ?? false,
                'quien_suministro_materiales' => $datos['quien_suministro_materiales'] ?? null,
                'usuario_asumio_costo' => $datos['usuario_asumio_costo'] ?? false,
                'valor_pagado' => $datos['valor_pagado'] ?? null,
                'medio_pago' => $datos['medio_pago'] ?? null,
                'entrego_factura' => $datos['entrego_factura'] ?? false,
                'tecnico_dejo_documento' => $datos['tecnico_dejo_documento'] ?? null,
                'informaron_no_estaba' => $datos['informaron_no_estaba'] ?? false,
                'autoriza' => $datos['autoriza'] ?? false,
                'firma_usuario' => $datos['firma_usuario'] ?? null,
                'cedula_usuario' => $datos['cedula_usuario'] ?? null,
                'firma_auditor' => $datos['firma_auditor'] ?? null,
                'cedula_auditor' => $datos['cedula_auditor'] ?? null,
                'urls_evidencia' => $datos['urls_evidencia'] ?? [],
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
            'auditoriaArchivosUrls' => $this->auditoriaArchivosUrls(),
        ]);
    }
}
