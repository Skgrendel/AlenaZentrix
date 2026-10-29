<?php

namespace App\Livewire\Informes;

use App\Enums\EstadoAuditoria;
use App\Enums\EstadoOrden;
use App\Enums\TipoServicio;
use App\Models\auditoria;
use App\Models\Convencion;
use App\Models\OrdenJob;
use App\Models\OrigenInformacion;
use App\Models\Proceso;
use App\Models\reportes;
use App\Models\ResultadoReporte;
use App\Models\TipoAnomalia;
use App\Models\TipoComercio;
use App\Models\TipologiaOrden;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.app')]
#[Title('Informes')]
/**
 * Módulo de generación y descarga del informe unificado en Excel.
 * Cada fila contiene la orden, su reporte de campo (si existe) y la auditoría (si existe).
 */
class InformesIndex extends Component
{
    public string $fechaInicio = '';

    public string $fechaFin = '';

    public string $estado = '';

    public string $servicio = '';

    public string $subTipo = '';

    public string $proceso = '';

    public string $origenInformacion = '';

    public bool $previewGenerado = false;

    public array $preview = [];

    public function estadoOptions(): array
    {
        return collect(EstadoOrden::cases())
            ->mapWithKeys(fn ($c) => [$c->value => $c->label()])
            ->all();
    }

    public function servicioOptions(): array
    {
        return collect(TipoServicio::cases())
            ->mapWithKeys(fn (TipoServicio $c) => [$c->value => $c->label()])
            ->all();
    }

    public function subTipoOptions(): array
    {
        return match ($this->servicio) {
            TipoServicio::GestionLegal->value => TipologiaOrden::query()
                ->where('activo', true)
                ->orderBy('nombre')
                ->pluck('nombre', 'id')
                ->toArray(),
            TipoServicio::Investigacion->value => [
                'interna' => 'Interna',
                'contratista' => 'Contratista',
                'Terceros' => 'Terceros',
            ],
            TipoServicio::AuditoriaCampo->value => [
                'visita_calidad_emergencia' => 'Visita de Calidad — Emergencia',
                'visita_calidad_trabajos_varios' => 'Visita de Calidad — Trabajos Varios',
                'visita_calidad_medidores_digitales' => 'Visita de Calidad — Medidores Digitales',
                'visita_calidad_suspensiones' => 'Visita de Calidad — Suspensiones',
            ],
            default => [],
        };
    }

    public function procesoOptions(): array
    {
        return Proceso::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->pluck('nombre', 'id')
            ->toArray();
    }

    public function origenInformacionOptions(): array
    {
        return OrigenInformacion::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->pluck('nombre', 'id')
            ->toArray();
    }

    public function updatedServicio(): void
    {
        $this->subTipo = '';
        $this->preview = [];
        $this->previewGenerado = false;
    }

    public function generarVista(): void
    {
        $this->preview = $this->buildPreviewData();
        $this->previewGenerado = true;
    }

    public function download(): StreamedResponse
    {
        [$headers, $rows, $filename] = $this->xlsxUnificado();

        return response()->streamDownload(function () use ($headers, $rows) {
            $writer = new Writer;
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValues($headers));
            foreach ($rows as $row) {
                $writer->addRow(Row::fromValues($row));
            }
            $writer->close();
        }, $filename.'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function baseQuery()
    {
        return OrdenJob::query()
            ->with([
                'personal',
                'proceso',
                'origenInformacion',
                'reportes' => fn ($q) => $q->latest()->with([
                    'auditoria',
                    'personal',
                    'comercio.tipoComercio',
                    'tipoImposibilidad',
                    'tipoRegulador',
                ]),
            ])
            ->when($this->fechaInicio, fn ($q) => $q->whereDate('created_at', '>=', $this->fechaInicio))
            ->when($this->fechaFin, fn ($q) => $q->whereDate('created_at', '<=', $this->fechaFin))
            ->when($this->estado, fn ($q) => $q->where('estado', $this->estado))
            ->when($this->servicio, fn ($q) => $q->where('tipo_servicio', $this->servicio))
            ->when($this->proceso, fn ($q) => $q->where('proceso_id', $this->proceso))
            ->when($this->origenInformacion, fn ($q) => $q->where('origen_informacion_id', $this->origenInformacion))
            ->when($this->subTipo, function ($q) {
                match ($this->servicio) {
                    TipoServicio::GestionLegal->value => $q->where('tipo_gestion_legal', $this->subTipo),
                    TipoServicio::Investigacion->value => $q->where('tipo_investigacion', $this->subTipo),
                    TipoServicio::AuditoriaCampo->value => $q->where('tipo_auditoria_campo', $this->subTipo),
                    default => null,
                };
            })
            ->orderBy('created_at', 'desc');
    }

    private function mapRow(OrdenJob $o, ?reportes $reporte = null): array
    {
        $reporte ??= $o->reportes->first();
        $auditoria = $reporte?->auditoria;
        $investigador = ($o->tipo_servicio === TipoServicio::Vigilancia && $reporte?->personal)
            ? $reporte->personal
            : $o->personal;

        return [
            // Orden
            $o->numero_orden,
            $o->proceso?->nombre ?? '',
            $o->origenInformacion?->nombre ?? '',
            $o->tipo_servicio->label(),
            $o->subtipoLabel(),
            $o->estado->label(),
            match ($o->resultado) {
                'positivo' => 'Positiva',
                'negativo' => 'Negativa',
                default => '',
            },
            $o->prioridad->label(),
            $investigador ? "{$investigador->nombres} {$investigador->apellidos}" : '',
            $o->cliente ?? '',
            $o->contrato ?? '',
            $o->direccion ?? '',
            $o->barrio ?? '',
            $o->medidor ?? '',
            $o->objetivo ?? '',
            $o->numero_noticia_criminal ?? '',
            $o->fecha_asignacion?->format('d/m/Y H:i') ?? '',
            $o->fecha_limite?->format('d/m/Y') ?? '',
            $o->fecha_completado?->format('d/m/Y H:i') ?? '',
            // Reporte del técnico
            $reporte ? 'Sí' : 'No',
            $reporte?->lectura ?? '',
            $reporte?->numero_medidor ?? '',
            $reporte?->comercio?->nombre_comercio ?? '',
            $reporte?->tipoImposibilidad?->nombre ?? '',
            $reporte?->tipoRegulador?->nombre ?? '',
            $reporte ? implode(' | ', array_filter(array_map(fn ($item) => is_array($item) ? ($item['actividades'] ?? '') : '', $reporte->actividades ?? []))) : '',
            // Columnas específicas del servicio/subtipo filtrado (ver columnasAdicionalesDefinicion())
            ...array_map(fn (array $columna) => ($columna['value'])($reporte, $o), $this->columnasAdicionalesDefinicion()),
            $reporte?->observaciones ?? '',
            $reporte?->conclusion ?? '',
            $reporte?->created_at?->format('d/m/Y H:i') ?? '',
            // Auditoría
            $auditoria instanceof auditoria
                ? ($auditoria->estado instanceof EstadoAuditoria ? $auditoria->estado->label() : ($auditoria->estado ?? ''))
                : 'Sin auditoría',
            $auditoria?->observaciones ?? '',
            $auditoria?->fecha_auditoria?->format('d/m/Y H:i') ?? '',
        ];
    }

    /**
     * Para Vigilancia expande una fila por cada turno registrado; si no tiene turnos aún,
     * devuelve una única fila con `$reporte = null`. Para los demás servicios devuelve una fila por orden.
     *
     * @return array<int, array{0: OrdenJob, 1: ?reportes}>
     */
    private function expandirFilasOrden(OrdenJob $o): array
    {
        if ($o->tipo_servicio === TipoServicio::Vigilancia && $o->reportes->isNotEmpty()) {
            return $o->reportes
                ->sortBy('created_at')
                ->values()
                ->map(fn (reportes $turno): array => [$o, $turno])
                ->all();
        }

        return [[$o, $o->reportes->first()]];
    }

    private function xlsxUnificado(): array
    {
        $headers = [
            'N° Orden', 'Proceso', 'Origen de Información', 'Servicio', 'Sub-tipo', 'Estado', 'Resultado', 'Prioridad', 'Investigador',
            'Cliente', 'Contrato', 'Dirección', 'Barrio', 'Medidor', 'Objetivo', 'N° Noticia Criminal',
            'Fecha Asignación', 'Fecha Límite', 'Fecha Completado',
            'Tiene Reporte', 'Lectura', 'N° Medidor Reporte', 'Comercio', 'Imposibilidad',
            'Tipo Regulador', 'Actividades', 'Observaciones Técnico', 'Conclusión', 'Fecha Reporte',
            'Estado Auditoría', 'Observaciones Auditoría', 'Fecha Auditoría',
        ];

        $columnasAdicionales = array_column($this->columnasAdicionalesDefinicion(), 'label');
        array_splice($headers, 26, 0, $columnasAdicionales);

        $rows = $this->baseQuery()
            ->get()
            ->flatMap(fn (OrdenJob $o) => array_map(
                fn (array $par) => $this->mapRow($par[0], $par[1]),
                $this->expandirFilasOrden($o)
            ))
            ->all();

        return [$headers, $rows, 'informe-unificado-'.now()->format('Y-m-d')];
    }

    protected function buildPreviewData(): array
    {
        return $this->baseQuery()
            ->limit(50)
            ->get()
            ->flatMap(fn (OrdenJob $o) => array_map(function (array $par): array {
                /** @var OrdenJob $orden */
                /** @var ?reportes $reporte */
                [$orden, $reporte] = $par;
                $auditoria = $reporte?->auditoria;
                $investigador = ($orden->tipo_servicio === TipoServicio::Vigilancia && $reporte?->personal)
                    ? $reporte->personal
                    : $orden->personal;

                return [
                    'N° Orden' => $orden->numero_orden,
                    'Proceso' => $orden->proceso?->nombre ?? '—',
                    'Origen de Información' => $orden->origenInformacion?->nombre ?? '—',
                    'Servicio' => $orden->tipo_servicio->label(),
                    'Sub-tipo' => $orden->subtipoLabel(),
                    'Estado' => $orden->estado->label(),
                    'Resultado' => match ($orden->resultado) {
                        'positivo' => 'Positiva',
                        'negativo' => 'Negativa',
                        default => '—',
                    },
                    'Investigador' => $investigador ? "{$investigador->nombres} {$investigador->apellidos}" : '—',
                    'Cliente' => $orden->cliente ?? '—',
                    'Tiene Reporte' => $reporte ? 'Sí' : 'No',
                    ...$this->columnasAdicionalesValores($orden, $reporte),
                    'Conclusión' => $reporte?->conclusion ?? '—',
                    'Estado Auditoría' => $auditoria instanceof auditoria
                        ? ($auditoria->estado instanceof EstadoAuditoria ? $auditoria->estado->label() : ($auditoria->estado ?? '—'))
                        : 'Sin auditoría',
                    'Fecha Completado' => $orden->fecha_completado?->format('d/m/Y') ?? '—',
                ];
            }, $this->expandirFilasOrden($o)))
            ->all();
    }

    /**
     * Columnas adicionales específicas del servicio/subtipo filtrado, para que la vista previa
     * y el Excel muestren lo realmente auditado.
     * Solo se activan cuando el usuario filtra por un servicio concreto, para no ensuciar
     * la vista "Todos los servicios" con columnas que no aplican a la mayoría de filas.
     *
     * @return array<int, array{label: string, value: \Closure(mixed $reporte, ?OrdenJob $orden=): string}>
     */
    private function columnasAdicionalesDefinicion(): array
    {
        return match ($this->servicio) {
            TipoServicio::AuditoriaCampo->value => $this->columnasAuditoriaCampo(),
            TipoServicio::Investigacion->value => $this->columnasInvestigacion(),
            TipoServicio::GestionLegal->value => $this->columnasGestionLegal(),
            TipoServicio::Gestion_Riesgo->value => $this->columnasGestionRiesgo(),
            TipoServicio::Vigilancia->value => $this->columnasVigilancia(),
            default => [],
        };
    }

    /** @var array<string, string>|null */
    private ?array $catalogoAnomalias = null;

    /** @var array<string, string>|null */
    private ?array $catalogoConvenciones = null;

    /** @var array<string, string>|null */
    private ?array $catalogoTiposComercio = null;

    /** @var array<string, string>|null */
    private ?array $catalogoResultadosReporte = null;

    /** @var array<string, string>|null */
    private ?array $catalogoTipologias = null;

    private function nombreConvencion(?string $id): string
    {
        if (! $id) {
            return '—';
        }

        $this->catalogoConvenciones ??= Convencion::withTrashed()->pluck('nombre', 'id')->all();

        return $this->catalogoConvenciones[$id] ?? '—';
    }

    private function nombreTipoComercio(?string $id): string
    {
        if (! $id) {
            return '—';
        }

        $this->catalogoTiposComercio ??= TipoComercio::withTrashed()->pluck('nombre', 'id')->all();

        return $this->catalogoTiposComercio[$id] ?? '—';
    }

    private function nombreResultadoReporte(?string $id): string
    {
        if (! $id) {
            return '—';
        }

        $this->catalogoResultadosReporte ??= ResultadoReporte::withTrashed()->pluck('nombre', 'id')->all();

        return $this->catalogoResultadosReporte[$id] ?? '—';
    }

    private function nombreTipologia(?string $id): string
    {
        if (! $id) {
            return '—';
        }

        $this->catalogoTipologias ??= TipologiaOrden::withTrashed()->pluck('nombre', 'id')->all();

        return $this->catalogoTipologias[$id] ?? $id;
    }

    /** @return array<int, array{label: string, value: \Closure(mixed $reporte, ?OrdenJob $orden=): string}> */
    private function columnasInvestigacion(): array
    {
        $dato = fn (string $key) => fn ($reporte) => $reporte?->datos_servicio[$key] ?? '—';

        return [
            ['label' => 'Convención', 'value' => fn ($reporte) => $this->nombreConvencion($reporte?->datos_servicio['convencion_id'] ?? null)],
            ['label' => 'Acompañamiento Técnico', 'value' => fn ($reporte) => match (true) {
                ($reporte?->datos_servicio['acompanamiento_tecnico'] ?? null) === null => '—',
                (bool) $reporte->datos_servicio['acompanamiento_tecnico'] => 'Sí',
                default => 'No',
            }],
            ['label' => 'Nombre Acompañante', 'value' => $dato('acompanante_nombre')],
            ['label' => 'Tipo Investigado', 'value' => $dato('tipo_investigado')],
            ['label' => 'Nombre Investigado', 'value' => $dato('nombre_investigado')],
            ['label' => 'Identificación Investigado', 'value' => $dato('identificacion')],
            ['label' => 'Comercio Investigado', 'value' => $dato('nombre_comercio_inv')],
            ['label' => 'Tipo Comercio Investigado', 'value' => fn ($reporte) => $this->nombreTipoComercio($reporte?->datos_servicio['tipo_comercio_inv_id'] ?? null)],
            ['label' => 'Resultado Reporte', 'value' => fn ($reporte) => $this->nombreResultadoReporte($reporte?->datos_servicio['resultado_reporte_id'] ?? null)],
        ];
    }

    /** @return array<int, array{label: string, value: \Closure(mixed $reporte, ?OrdenJob $orden=): string}> */
    private function columnasGestionLegal(): array
    {
        $dato = fn (string $key) => fn ($reporte) => $reporte?->datos_servicio[$key] ?? '—';

        return [
            ['label' => 'Tipología', 'value' => fn ($reporte, ?OrdenJob $orden = null) => $this->nombreTipologia($reporte?->datos_servicio['tipologia_orden_id'] ?? $orden?->tipo_gestion_legal)],
            ['label' => 'Fecha Ejecución', 'value' => $dato('fecha_ejecucion')],
            ['label' => 'Departamento', 'value' => $dato('departamento')],
            ['label' => 'Municipio', 'value' => $dato('municipio')],
            ['label' => 'Radio Objetivo', 'value' => $dato('radio_objetivo')],
            ['label' => 'Resultado Reporte', 'value' => fn ($reporte) => $this->nombreResultadoReporte($reporte?->datos_servicio['resultado_reporte_id'] ?? null)],
        ];
    }

    /** @return array<int, array{label: string, value: \Closure(mixed $reporte, ?OrdenJob $orden=): string}> */
    private function columnasGestionRiesgo(): array
    {
        return [
            ['label' => 'Actividades Realizadas', 'value' => fn ($reporte) => $reporte
                ? (implode(' | ', array_filter(array_map(fn ($item) => is_array($item) ? ($item['actividades'] ?? '') : '', $reporte->actividades ?? []))) ?: '—')
                : '—'],
            ['label' => 'Observaciones Técnico', 'value' => fn ($reporte) => $reporte?->observaciones ?: '—'],
            ['label' => 'URL Video', 'value' => fn ($reporte) => $reporte?->video ?: '—'],
            ['label' => 'N° Evidencias', 'value' => fn ($reporte) => (string) count((array) ($reporte?->imagenes ?? []))],
        ];
    }

    /** @return array<int, array{label: string, value: \Closure(mixed $reporte, ?OrdenJob $orden=): string}> */
    private function columnasVigilancia(): array
    {
        $yesNo = fn (?bool $v): string => match (true) {
            $v === null => '—',
            $v => 'Sí',
            default => 'No',
        };
        $dato = fn (string $key) => fn ($reporte) => $reporte?->datos_servicio[$key] ?? '—';

        return [
            ['label' => 'Fecha Turno', 'value' => $dato('fecha_turno')],
            ['label' => 'Hora Inicio', 'value' => $dato('hora_inicio')],
            ['label' => 'Hora Fin', 'value' => $dato('hora_fin')],
            ['label' => '¿Hay Novedad?', 'value' => fn ($reporte) => $yesNo($reporte?->datos_servicio['hay_novedad'] ?? null)],
            ['label' => 'Descripción Novedad', 'value' => $dato('descripcion_novedad')],
            ['label' => '¿Hay Reincidencia?', 'value' => fn ($reporte) => $yesNo($reporte?->datos_servicio['hay_reincidencia'] ?? null)],
            ['label' => 'Resultado Turno', 'value' => fn ($reporte) => $this->nombreResultadoReporte($reporte?->datos_servicio['resultado_reporte_id'] ?? null)],
        ];
    }

    /**
     * Nombres de anomalías separados por coma; el catálogo se carga una sola vez para evitar N+1.
     *
     * @param  array<int, string>|mixed  $ids
     */
    private function nombresAnomalias(mixed $ids): string
    {
        $ids = array_filter((array) $ids);

        if ($ids === []) {
            return '—';
        }

        $this->catalogoAnomalias ??= TipoAnomalia::withTrashed()->pluck('nombre', 'id')->all();

        $nombres = array_filter(array_map(fn ($id) => $this->catalogoAnomalias[$id] ?? null, $ids));

        return $nombres === [] ? '—' : implode(', ', $nombres);
    }

    /** @return array<int, array{label: string, value: \Closure(mixed $reporte, ?OrdenJob $orden=): string}> */
    private function columnasAuditoriaCampo(): array
    {
        $yesNo = fn (?bool $v): string => match (true) {
            $v === null => '—',
            $v => 'Sí',
            default => 'No',
        };
        $dato = fn (string $key) => fn ($reporte) => $reporte?->datos_servicio[$key] ?? '—';

        if ($this->subTipo === 'visita_calidad_medidores_digitales') {
            return [
                ['label' => 'Fecha y Hora Atención', 'value' => $dato('fecha_hora_atencion')],
                ['label' => 'Técnico que Atendió', 'value' => $dato('tecnico_atendio_medidor')],
                ['label' => 'Nombre Atiende', 'value' => $dato('nombre_atiende')],
                ['label' => 'N° Medidor Instalado', 'value' => $dato('numero_medidor_instalado')],
                ['label' => 'Teléfono Contacto', 'value' => $dato('celular_atiende')],
                ['label' => 'Resultado Auditoría', 'value' => fn ($reporte) => match ($reporte?->datos_servicio['resultado_medidor'] ?? null) {
                    'conforme' => 'Conforme',
                    'no_conforme' => 'No conforme',
                    'seguimiento_urgente' => 'Requiere seguimiento urgente',
                    default => '—',
                }],
                ['label' => 'Informó Gratuidad', 'value' => fn ($reporte) => $yesNo($reporte?->datos_servicio['informo_gratuidad_medidor'] ?? null)],
                ['label' => 'Solicitó/Recibió Dinero', 'value' => fn ($reporte) => $yesNo($reporte?->datos_servicio['solicito_dinero_medidor'] ?? null)],
                ['label' => 'Gestión Conforme', 'value' => fn ($reporte) => $yesNo($reporte?->datos_servicio['gestion_conforme_medidor'] ?? null)],
                ['label' => 'Requiere Seguimiento', 'value' => fn ($reporte) => $yesNo($reporte?->datos_servicio['requiere_seguimiento_medidor'] ?? null)],
                ['label' => 'Hallazgos', 'value' => $dato('hallazgos_medidor')],
            ];
        }

        if ($this->subTipo === 'visita_calidad_suspensiones') {
            return [
                ['label' => 'Categoría en campo', 'value' => fn ($reporte) => ($c = $reporte?->datos_servicio['categoria_campo'] ?? null) ? ucfirst((string) $c) : '—'],
                ['label' => '¿Suspensión ejecutada?', 'value' => fn ($reporte) => $yesNo($reporte?->datos_servicio['suspension_ejecutada'] ?? null)],
                ['label' => 'Método instalado', 'value' => fn ($reporte) => match ($reporte?->datos_servicio['metodo_suspension'] ?? null) {
                    'sonda_ips' => 'Sonda (Dispositivo IPS)',
                    'sonda_cts' => 'Sonda (Dispositivo CTS)',
                    'taponamiento_acometida' => 'Taponamiento de acometida',
                    'taponamiento_multifamiliar' => 'Taponamiento Multifamiliar',
                    default => '—',
                }],
                ['label' => 'Serie sonda', 'value' => $dato('serie_sonda')],
                ['label' => 'Serie Caps', 'value' => $dato('serie_caps')],
                ['label' => 'Motivo no ejecución', 'value' => fn ($reporte) => match ($reporte?->datos_servicio['motivo_no_ejecucion'] ?? null) {
                    'no_ubico_direccion' => 'No se ubicó la dirección',
                    'predio_cerrado' => 'Predio cerrado o desocupado',
                    'usuario_impide' => 'El usuario impide el procedimiento',
                    'riesgo_sitio' => 'Riesgo en sitio (perro, altura, etc)',
                    'redes_terraza' => 'Redes por terraza',
                    'usuario_taponado' => 'Se encontró el usuario taponado en campo',
                    default => '—',
                }],
                ['label' => 'N° fotos fachada', 'value' => fn ($reporte) => (string) count((array) ($reporte?->datos_servicio['fotos_fachada'] ?? []))],
                ['label' => 'N° fotos suspensión', 'value' => fn ($reporte) => (string) count((array) ($reporte?->datos_servicio['fotos_suspension'] ?? []))],
                ['label' => 'Lectura medidor', 'value' => $dato('lectura_medidor')],
                ['label' => 'Lectura referencia', 'value' => fn ($reporte, ?OrdenJob $orden = null) => $orden?->lectura_referencia ?? '—'],
                ['label' => 'Coincide lectura', 'value' => fn ($reporte, ?OrdenJob $orden = null) => $yesNo($orden?->lecturaCoincide($reporte?->datos_servicio['lectura_medidor'] ?? null))],
                ['label' => 'Anomalías medidor', 'value' => fn ($reporte) => $this->nombresAnomalias($reporte?->datos_servicio['anomalias_medidor'] ?? [])],
                ['label' => 'Condición sellos', 'value' => $dato('condicion_sellos')],
                ['label' => 'Elementos rotos', 'value' => $dato('elementos_rotos')],
                ['label' => 'Justificación no ejecución', 'value' => $dato('justificacion_no_ejecucion')],
                ['label' => 'N° fotos medidor', 'value' => fn ($reporte) => (string) count((array) ($reporte?->datos_servicio['fotos_medidor'] ?? []))],
                ['label' => 'N° fotos no ejecución', 'value' => fn ($reporte) => (string) count((array) ($reporte?->datos_servicio['fotos_no_ejecucion'] ?? []))],
            ];
        }

        if (in_array($this->subTipo, ['visita_calidad_emergencia', 'visita_calidad_trabajos_varios'], true)) {
            return [
                ['label' => 'Recibió Visita', 'value' => fn ($reporte) => $yesNo($reporte?->datos_servicio['recibio_visita'] ?? null)],
                ['label' => 'Se Realizó Revisión', 'value' => fn ($reporte) => $yesNo($reporte?->datos_servicio['se_realizo_revision'] ?? null)],
                ['label' => 'Fecha Ejecución', 'value' => $dato('fecha_ejecucion')],
                ['label' => 'Descripción Trabajo', 'value' => $dato('descripcion_trabajo')],
                ['label' => 'Fuga/Falla Detectada', 'value' => fn ($reporte) => $yesNo($reporte?->datos_servicio['se_detecto_fuga'] ?? null)],
                ['label' => 'Problema Persiste', 'value' => fn ($reporte) => $yesNo($reporte?->datos_servicio['problema_persiste'] ?? null)],
                ['label' => 'Servicio Correcto', 'value' => fn ($reporte) => $yesNo($reporte?->datos_servicio['servicio_correcto'] ?? null)],
                ['label' => 'Se Usaron Materiales', 'value' => fn ($reporte) => $yesNo($reporte?->datos_servicio['se_usaron_materiales'] ?? null)],
                ['label' => 'Usuario Asumió Costo', 'value' => fn ($reporte) => $yesNo($reporte?->datos_servicio['usuario_asumio_costo'] ?? null)],
                ['label' => 'Entregó Factura/IVT', 'value' => fn ($reporte) => $yesNo($reporte?->datos_servicio['entrego_factura'] ?? null)],
            ];
        }

        // Servicio Auditoría de Campo sin subtipo elegido: los subtipos tienen estructuras de
        // datos distintas, así que no mezclamos columnas hasta que el usuario elija uno.
        return [];
    }

    /** @return array<string, string> */
    private function columnasAdicionalesValores(OrdenJob $o, $reporte): array
    {
        return collect($this->columnasAdicionalesDefinicion())
            ->mapWithKeys(fn (array $columna) => [$columna['label'] => ($columna['value'])($reporte, $o)])
            ->all();
    }

    public function render(): View
    {
        return view('livewire.informes.informes-index', [
            'estadoOptions' => $this->estadoOptions(),
            'servicioOptions' => $this->servicioOptions(),
            'subTipoOptions' => $this->subTipoOptions(),
            'procesoOptions' => $this->procesoOptions(),
            'origenInformacionOptions' => $this->origenInformacionOptions(),
        ]);
    }
}
