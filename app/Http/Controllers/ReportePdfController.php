<?php

namespace App\Http\Controllers;

use App\Enums\TipoServicio;
use App\Models\Convencion;
use App\Models\OrdenJob;
use App\Models\reportes;
use App\Models\ResultadoReporte;
use App\Models\TipoAnomalia;
use App\Models\TipoComercio;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class ReportePdfController extends Controller
{
    public function DownLoadInformeFraude(OrdenJob $orden): Response
    {
        abort_unless($orden->tipo_servicio === TipoServicio::Fraudes, 403);
        abort_unless(auth()->user()->can('informes'), 403);

        ini_set('memory_limit', '256M');

        $reporteId = request()->query('reporte');

        $orden->load(['personal', 'creadoPor']);

        $query = $orden->reportes()->with([
            'comercio.tipoComercio',
            'tipoImposibilidad',
            'tipoRegulador',
            'marcaMedidor',
            'marcaRegulador',
            'tipoNotificacionAlerta',
            'auditoria.auditadoPor.personal',
        ]);

        $reporte = $reporteId
            ? $query->where('id', $reporteId)->firstOrFail()
            : $query->latest()->firstOrFail();

        $anomalias = collect($reporte->anomalia ?? [])
            ->map(fn (string|int $id) => TipoAnomalia::find($id)?->nombre)
            ->filter()
            ->implode(', ');

        $logoProderi = $this->fileToBase64(public_path('assets/image/LOGO PRODERI CON SLOGAN.png'), 'image/png');

        $imagenes = $this->resolveImagenes($reporte->imagenes ?? []);

        /** @var User $firmante */
        $firmante = auth()->user();

        $firma = [
            'nombre' => $firmante->fullName(),
            'cargo' => $firmante->personal?->cargo,
            'email' => $firmante->email,
            'ip' => request()->ip(),
            'agente' => request()->userAgent() ?? 'No disponible',
            'fecha' => now()->format('d/m/Y H:i:s'),
        ];

        $objetivos = $this->resolveObjetivos($orden->tipo_servicio->value);

        $pdf = Pdf::loadView('pdf.reporte-fraude', [
            'orden' => $orden,
            'reporte' => $reporte,
            'anomalias' => $anomalias,
            'logoProderi' => $logoProderi,
            'imagenes' => $imagenes,
            'fechaGeneracion' => now()->format('d-m-Y'),
            'firma' => $firma,
            'objetivos' => $objetivos,
        ]);

        $pdf->setPaper('A4', 'portrait');

        return $pdf->download("reporte-fraude-{$orden->numero_orden}.pdf");
    }

    public function DownLoadInformeInvestigacion(OrdenJob $orden): Response
    {
        abort_unless($orden->tipo_servicio === TipoServicio::Investigacion, 403);
        abort_unless(auth()->user()->can('informes'), 403);

        ini_set('memory_limit', '256M');

        $reporteId = request()->query('reporte');

        $orden->load(['personal', 'creadoPor']);

        $query = $orden->reportes()->with([
            'personal.usuario',
            'comercio.tipoComercio',
            'tipoImposibilidad',
            'tipoRegulador',
            'marcaMedidor',
            'marcaRegulador',
            'tipoNotificacionAlerta',
            'auditoria.auditadoPor.personal',
        ]);

        $reporte = $reporteId
            ? $query->where('id', $reporteId)->firstOrFail()
            : $query->latest()->firstOrFail();

        $anomalias = collect($reporte->anomalia ?? [])
            ->map(fn (string|int $id) => TipoAnomalia::find($id)?->nombre)
            ->filter()
            ->implode(', ');

        $logoProderi = $this->fileToBase64(public_path('assets/image/LOGO PRODERI CON SLOGAN.png'), 'image/png');

        $imagenes = $this->resolveImagenes($reporte->imagenes ?? []);

        $investigador = $reporte->personal;

        $firma = [
            'nombre' => trim(($investigador?->nombres ?? '').' '.($investigador?->apellidos ?? '')) ?: 'Sin nombre',
            'cargo' => $investigador?->cargo,
            'email' => $investigador?->usuario?->email ?? '—',
            'ip' => request()->ip(),
            'agente' => request()->userAgent() ?? 'No disponible',
            'fecha' => $reporte->created_at?->format('d/m/Y H:i:s') ?? now()->format('d/m/Y H:i:s'),
        ];

        $datosOrden = $this->buildDatosOrden($orden);
        $datosReporte = $this->buildDatosReporteInvestigacion($reporte);

        $pdf = Pdf::loadView('pdf.reporte-investigacion', [
            'orden' => $orden,
            'reporte' => $reporte,
            'anomalias' => $anomalias,
            'logoProderi' => $logoProderi,
            'imagenes' => $imagenes,
            'fechaGeneracion' => now()->format('d-m-Y'),
            'firma' => $firma,
            'objetivos' => $orden->objetivos,
            'datosOrden' => $datosOrden,
            'datosReporte' => $datosReporte,
        ]);

        $pdf->setPaper('A4', 'portrait');

        return $pdf->download("reporte-investigacion-{$orden->numero_orden}.pdf");
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDatosOrden(OrdenJob $orden): array
    {
        return [
            'numero_orden' => $orden->numero_orden,
            'servicio' => $orden->tipo_servicio?->label() ?? '—',
            'prioridad' => $orden->prioridad?->label() ?? '—',
            'estado' => $orden->estado?->label() ?? '—',
            'tecnico' => trim(($orden->personal?->nombres ?? '').' '.($orden->personal?->apellidos ?? '')) ?: 'Sin asignar',
            'contrato' => $orden->contrato ?: '—',
            'cliente' => $orden->cliente ?: '—',
            'direccion' => trim($orden->direccion.($orden->barrio ? ', '.$orden->barrio : '')) ?: '—',
            'medidor' => $orden->medidor ?: '—',
            'tipo_medidor' => $this->formatTipoMedidor($orden->tipo_medidor),
            'ciclo' => $orden->ciclo ?: '—',
            'tipo_investigacion' => $orden->tipo_investigacion,
            'antecedente' => $orden->antecedente ?: '—',
            'objetivo' => $orden->objetivo ?: '—',
            'actividades' => collect($orden->actividades ?? [])
                ->pluck('descripcion')
                ->filter()
                ->implode('; ') ?: '—',
            'noticia_criminal' => $orden->numero_noticia_criminal ?: '—',
            'fecha_asignacion' => $orden->fecha_asignacion?->format('d/m/Y H:i') ?? '—',
            'fecha_limite' => $orden->fecha_limite?->format('d/m/Y H:i') ?? '—',
            'fecha_completado' => $orden->fecha_completado?->format('d/m/Y H:i') ?? '—',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDatosReporteInvestigacion(reportes $reporte): array
    {
        $datos = $reporte->datos_servicio ?? [];

        return [
            'convencion' => Convencion::find($datos['convencion_id'] ?? null)?->nombre ?? '—',
            'acompanamiento_tecnico' => (bool) ($datos['acompanamiento_tecnico'] ?? false),
            'acompanante_nombre' => $datos['acompanante_nombre'] ?? '—',
            'acompanante_identificacion' => $datos['acompanante_identificacion'] ?? '—',
            'acompanante_cargo' => $datos['acompanante_cargo'] ?? '—',
            'acompanante_cuadrilla' => $datos['acompanante_cuadrilla'] ?? '—',
            'tipo_investigado' => $datos['tipo_investigado'] ?? null,
            'nombre_investigado' => $datos['nombre_investigado'] ?? '—',
            'identificacion' => $datos['identificacion'] ?? '—',
            'descripcion_fisica' => $datos['descripcion_fisica'] ?? '—',
            'nombre_comercio_inv' => $datos['nombre_comercio_inv'] ?? '—',
            'tipo_comercio_inv' => TipoComercio::find($datos['tipo_comercio_inv_id'] ?? null)?->nombre ?? '—',
            'resultado' => ResultadoReporte::find($datos['resultado_reporte_id'] ?? null)?->nombre ?? '—',
            'actividades' => $this->normalizeActividades($reporte->actividades ?? '[]'),
            'observaciones' => $reporte->observaciones ?: 'Sin observaciones registradas.',
            'conclusion' => $reporte->conclusion ?: null,
        ];
    }

    /**
     * @param  array<int, mixed>|string  $actividades
     * @return array<int, array{descripcion_orden: string, actividades: string}>
     */
    private function normalizeActividades(array|string $actividades): array
    {
        if (is_string($actividades)) {
            $actividades = json_decode($actividades, true) ?? [];
        }

        return collect($actividades)
            ->map(function (mixed $actividad): ?array {
                if (is_array($actividad)) {
                    $asignada = trim($actividad['descripcion_orden'] ?? '');
                    $respuesta = trim($actividad['actividades'] ?? '');

                    if (! $asignada && ! $respuesta) {
                        return null;
                    }

                    return ['descripcion_orden' => $asignada, 'actividades' => $respuesta];
                }

                if (is_string($actividad) && trim($actividad) !== '') {
                    return ['descripcion_orden' => '', 'actividades' => trim($actividad)];
                }

                return null;
            })
            ->filter()
            ->values()
            ->all();
    }

    private function formatTipoMedidor(?string $tipoMedidor): string
    {
        return match (strtolower((string) $tipoMedidor)) {
            'analogico', 'analógico' => 'Analógico',
            'digital' => 'Digital',
            'por_definir' => 'Por definir',
            default => $tipoMedidor ?: '—',
        };
    }

    /**
     * Fetches images from S3 and converts each to a base64 data URI for embedding in the PDF.
     *
     * @param  array<int, string>  $paths
     * @return array<int, string>
     */
    private function resolveImagenes(array $paths): array
    {
        $disk = Storage::disk('s3');

        return collect($paths)
            ->map(function (string $path) use ($disk): ?string {
                try {
                    if (! $disk->exists($path)) {
                        return null;
                    }
                    $mimeType = $disk->mimeType($path);
                    $content = $disk->get($path);

                    return 'data:'.$mimeType.';base64,'.base64_encode($content);
                } catch (\Throwable) {
                    return null;
                }
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{numero: string, items: array<int, string>}>
     */
    private function resolveObjetivos(string $tipoServicio): array
    {
        $path = resource_path('data/objetivos-pdf.json');

        if (! file_exists($path)) {
            return [];
        }

        $data = json_decode(file_get_contents($path), true);

        return $data[$tipoServicio] ?? [];
    }

    private function fileToBase64(string $path, string $mimeType): string
    {
        return 'data:'.$mimeType.';base64,'.base64_encode(file_get_contents($path));
    }
}
