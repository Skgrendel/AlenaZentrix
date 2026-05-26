<?php

namespace App\Livewire\Auditor;

use App\Enums\EstadoAuditoria;
use App\Enums\TipoServicio;
use App\Models\auditoria;
use App\Models\Convencion;
use App\Models\ResultadoReporte;
use App\Models\TipoComercio;
use App\Models\TipologiaOrden;
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
            'reporte.personal',
            'reporte.tipoImposibilidad',
            'reporte.tipoRegulador',
            'reporte.marcaMedidor',
            'reporte.marcaRegulador',
            'reporte.tipoNotificacionAlerta',
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

    /**
     * @return array<int, array{url: string, nombre: string, es_imagen: bool}>
     */
    public function archivosUrls(): array
    {
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'tiff', 'tif'];

        return collect($this->auditoria->reporte?->imagenes ?? [])
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
     * Normaliza `datos_servicio` del reporte según el tipo de servicio de la orden.
     *
     * @return array<string, mixed>
     */
    public function datosServicioDisplay(): array
    {
        $datos = $this->auditoria->reporte?->datos_servicio ?? [];
        $orden = $this->auditoria->reporte?->ordenJob;
        $tipo = $orden?->tipo_servicio;

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
            $label = TipologiaOrden::find($orden?->tipo_gestion_legal ?? null)?->nombre;

            return [
                'tipo_gestion_label' => $label ?? ($orden?->tipo_gestion_legal ?? '—'),
                'numero_noticia_criminal' => $orden?->numero_noticia_criminal,
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

        if ($tipo === TipoServicio::AuditoriaCampo) {
            $subtipo = match ($orden?->tipo_auditoria_campo) {
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
        return view('livewire.auditor.auditoria-revisar', [
            'reporte' => $this->auditoria->reporte,
            'orden' => $this->auditoria->reporte?->ordenJob,
            'imagenUrls' => $this->imagenUrls(),
            'archivosUrls' => $this->archivosUrls(),
            'datosServicio' => $this->datosServicioDisplay(),
        ]);
    }
}
