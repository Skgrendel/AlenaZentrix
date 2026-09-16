<?php

namespace App\Http\Controllers;

use App\Enums\TipoServicio;
use App\Models\Convencion;
use App\Models\OrdenJob;
use App\Models\reportes;
use App\Models\ResultadoReporte;
use App\Models\TipoAnomalia;
use App\Models\TipoComercio;
use App\Models\TipologiaOrden;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportePdfController extends Controller
{
    public function DownLoadInformeFraude(OrdenJob $orden): Response
    {
        abort_unless($orden->tipo_servicio === TipoServicio::Fraudes, 403);
        abort_unless(auth()->user()->can('informes'), 403);

        ini_set('memory_limit', '256M');

        $reporteId = request()->query('reporte');

        $orden->load(['personal', 'creadoPor', 'proceso']);

        $query = $orden->reportes()->with([
            'comercio.tipoComercio',
            'tipoImposibilidad',
            'tipoRegulador',
            'marcaMedidor',
            'marcaRegulador',
            'tipoNotificacionAlerta',
            'auditoria.auditadoPor.personal',
            'auditoria.tipoAnomalia',
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

        $imageExtensions = ['jpg', 'jpeg', 'jfif', 'png', 'gif', 'webp', 'bmp', 'tiff', 'tif', 'heic', 'heif', 'avif'];
        $auditoriaEvidencias = collect($reporte->auditoria?->evidencias ?? []);
        $auditoriaImagenes = $this->resolveImagenes(
            $auditoriaEvidencias->filter(fn (string $p) => in_array(strtolower(pathinfo($p, PATHINFO_EXTENSION)), $imageExtensions))->values()->all()
        );
        $auditoriaDocs = $auditoriaEvidencias
            ->filter(fn (string $p) => ! in_array(strtolower(pathinfo($p, PATHINFO_EXTENSION)), $imageExtensions))
            ->map(fn (string $p) => basename($p))
            ->values()
            ->all();

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
            'auditoriaImagenes' => $auditoriaImagenes,
            'auditoriaDocs' => $auditoriaDocs,
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

        $orden->load(['personal', 'creadoPor', 'proceso']);

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

    public function DownLoadInformeGestionLegal(OrdenJob $orden): Response
    {
        abort_unless($orden->tipo_servicio === TipoServicio::GestionLegal, 403);
        abort_unless(auth()->user()->can('informes'), 403);

        ini_set('memory_limit', '256M');

        $reporteId = request()->query('reporte');

        $orden->load(['personal', 'creadoPor', 'proceso']);

        $query = $orden->reportes()->with([
            'personal.usuario',
            'auditoria.auditadoPor.personal',
        ]);

        $reporte = $reporteId
            ? $query->where('id', $reporteId)->firstOrFail()
            : $query->latest()->firstOrFail();

        $logoProderi = $this->fileToBase64(public_path('assets/image/LOGO PRODERI CON SLOGAN.png'), 'image/png');

        $imageExtensions = ['jpg', 'jpeg', 'jfif', 'png', 'gif', 'webp', 'bmp', 'tiff', 'tif', 'heic', 'heif', 'avif'];
        $imagePaths = collect($reporte->imagenes ?? [])
            ->filter(fn (string $path) => in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), $imageExtensions, true))
            ->values()
            ->all();

        $imagenes = $this->resolveImagenes($imagePaths);

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
        $datosOrden['tipo_gestion_label'] = TipologiaOrden::find($orden->tipo_gestion_legal ?? null)?->nombre ?? ($orden->tipo_gestion_legal ?? '—');

        $datosReporte = $this->buildDatosReporteGestionLegal($reporte);

        $pdf = Pdf::loadView('pdf.reporte-gestion-legal', [
            'orden' => $orden,
            'reporte' => $reporte,
            'logoProderi' => $logoProderi,
            'imagenes' => $imagenes,
            'fechaGeneracion' => now()->format('d-m-Y'),
            'firma' => $firma,
            'datosOrden' => $datosOrden,
            'datosReporte' => $datosReporte,
        ]);

        $pdf->setPaper('A4', 'portrait');

        return $pdf->download("reporte-gestion-legal-{$orden->numero_orden}.pdf");
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDatosOrden(OrdenJob $orden): array
    {
        return [
            'numero_orden' => $orden->numero_orden,
            'proceso' => $orden->proceso?->nombre ?? null,
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
            'geo_referencia' => $reporte->geo_referencia ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDatosReporteGestionLegal(reportes $reporte): array
    {
        $datos = $reporte->datos_servicio ?? [];

        return [
            'tipologia' => TipologiaOrden::find($datos['tipologia_orden_id'] ?? null)?->nombre ?? null,
            'fecha_ejecucion' => $datos['fecha_ejecucion'] ?? null,
            'departamento' => $datos['departamento'] ?? null,
            'municipio' => $datos['municipio'] ?? null,
            'radio_objetivo' => $datos['radio_objetivo'] ?? null,
            'actividades' => $this->normalizeActividades($reporte->actividades ?? '[]'),
            'resultado' => ResultadoReporte::find($datos['resultado_reporte_id'] ?? null)?->nombre ?? '—',
            'observaciones' => $reporte->observaciones ?: 'Sin observaciones registradas.',
            'conclusion' => $reporte->conclusion ?: null,
            'geo_referencia' => $reporte->geo_referencia ?? null,
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
     * Fetches images from S3, downscales/recompresses them, and converts each to a base64
     * data URI for embedding in the PDF. Downscaling keeps memory usage bounded when a report
     * (e.g. vigilancia) embeds many full-resolution phone photos in a single document, since
     * dompdf decodes every embedded image to a full bitmap while rendering.
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

                    return $this->downscaleImageToBase64($disk->get($path));
                } catch (\Throwable) {
                    return null;
                }
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Downscales an image to fit within $maxDimension and re-encodes it as JPEG at $quality
     * before base64-encoding. Formats GD cannot decode (e.g. heic, tiff) are embedded as-is.
     */
    private function downscaleImageToBase64(string $content, int $maxDimension = 1200, int $quality = 75): string
    {
        $image = @imagecreatefromstring($content);

        if ($image === false) {
            $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->buffer($content) ?: 'application/octet-stream';

            return 'data:'.$mimeType.';base64,'.base64_encode($content);
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, $maxDimension / max($width, $height));

        if ($scale < 1) {
            $newWidth = max(1, (int) round($width * $scale));
            $newHeight = max(1, (int) round($height * $scale));

            $resized = imagecreatetruecolor($newWidth, $newHeight);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($image);
            $image = $resized;
        }

        ob_start();
        imagejpeg($image, null, $quality);
        $data = ob_get_clean();
        imagedestroy($image);

        return 'data:image/jpeg;base64,'.base64_encode($data);
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

    public function DownLoadInformeAuditoriaCampo(OrdenJob $orden): Response
    {
        abort_unless($orden->tipo_servicio === TipoServicio::AuditoriaCampo, 403);
        abort_unless(auth()->user()->can('informes'), 403);

        ini_set('memory_limit', '256M');

        $reporteId = request()->query('reporte');

        $orden->load(['personal', 'creadoPor', 'proceso']);

        $query = $orden->reportes()->with([
            'personal.usuario',
            'auditoria.auditadoPor.personal',
        ]);

        $reporte = $reporteId
            ? $query->where('id', $reporteId)->firstOrFail()
            : $query->latest()->firstOrFail();

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

        $subtipos = [
            'visita_calidad_emergencia' => 'Visita de Calidad — Emergencia',
            'visita_calidad_trabajos_varios' => 'Visita de Calidad — Trabajos Varios',
            'visita_calidad_medidores_digitales' => 'Visita de Calidad — Medidores Digitales',
        ];

        $datosOrden = $this->buildDatosOrden($orden);
        $datosOrden['subtipo'] = $subtipos[$orden->tipo_auditoria_campo ?? ''] ?? ($orden->tipo_auditoria_campo ?? '—');

        $datosReporte = $this->buildDatosReporteAuditoriaCampo($reporte);

        $pdf = Pdf::loadView('pdf.reporte-auditoria-campo', [
            'orden' => $orden,
            'reporte' => $reporte,
            'logoProderi' => $logoProderi,
            'imagenes' => $imagenes,
            'fechaGeneracion' => now()->format('d-m-Y'),
            'firma' => $firma,
            'datosOrden' => $datosOrden,
            'datosReporte' => $datosReporte,
        ]);

        $pdf->setPaper('A4', 'portrait');

        return $pdf->download("reporte-auditoria-campo-{$orden->numero_orden}.pdf");
    }

    /**
     * @return array<string, mixed>
     */
    public function DownLoadInformeGestionRiesgo(OrdenJob $orden): Response
    {
        abort_unless($orden->tipo_servicio === TipoServicio::Gestion_Riesgo, 403);
        abort_unless(auth()->user()->can('informes'), 403);

        ini_set('memory_limit', '256M');

        $reporteId = request()->query('reporte');

        $orden->load(['personal', 'creadoPor', 'proceso']);

        $query = $orden->reportes()->with([
            'personal.usuario',
            'auditoria.auditadoPor.personal',
        ]);

        $reporte = $reporteId
            ? $query->where('id', $reporteId)->firstOrFail()
            : $query->latest()->firstOrFail();

        $logoProderi = $this->fileToBase64(public_path('assets/image/LOGO PRODERI CON SLOGAN.png'), 'image/png');

        $imageExtensions = ['jpg', 'jpeg', 'jfif', 'png', 'gif', 'webp', 'bmp', 'tiff', 'tif', 'heic', 'heif', 'avif'];
        $imagePaths = collect($reporte->imagenes ?? [])
            ->filter(fn (string $path) => in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), $imageExtensions, true))
            ->values()
            ->all();

        $imagenes = $this->resolveImagenes($imagePaths);

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
        $datosReporte = $this->buildDatosReporteGestionRiesgo($reporte);

        $pdf = Pdf::loadView('pdf.reporte-gestion-riesgo', [
            'orden' => $orden,
            'reporte' => $reporte,
            'logoProderi' => $logoProderi,
            'imagenes' => $imagenes,
            'fechaGeneracion' => now()->format('d-m-Y'),
            'firma' => $firma,
            'datosOrden' => $datosOrden,
            'datosReporte' => $datosReporte,
        ]);

        $pdf->setPaper('A4', 'portrait');

        return $pdf->download("reporte-gestion-riesgo-{$orden->numero_orden}.pdf");
    }

    public function DownLoadInformeVigilancia(OrdenJob $orden): Response
    {
        abort_unless($orden->tipo_servicio === TipoServicio::Vigilancia, 403);
        abort_unless(auth()->user()->can('informes'), 403);

        ini_set('memory_limit', '256M');

        $orden->load(['personal', 'creadoPor', 'proceso']);

        $reportes = $orden->reportes()
            ->with(['personal.usuario'])
            ->orderBy('created_at', 'asc')
            ->get();

        $resultadoIds = $reportes
            ->map(fn (reportes $r) => $r->datos_servicio['resultado_reporte_id'] ?? null)
            ->filter()
            ->unique()
            ->values();

        $resultados = ResultadoReporte::whereIn('id', $resultadoIds)->pluck('nombre', 'id')->all();

        $logoProderi = $this->fileToBase64(public_path('assets/image/LOGO PRODERI CON SLOGAN.png'), 'image/png');

        $imagenesPerTurno = [];
        foreach ($reportes as $reporte) {
            $imagenesPerTurno[$reporte->id] = $this->resolveImagenes($reporte->imagenes ?? []);
        }

        /** @var User $generadorUser */
        $generadorUser = auth()->user();
        $generador = [
            'nombre' => $generadorUser->fullName(),
            'email' => $generadorUser->email,
            'fecha' => now()->format('d/m/Y H:i:s'),
        ];

        $datosOrden = $this->buildDatosOrden($orden);

        $pdf = Pdf::loadView('pdf.reporte-vigilancia', [
            'orden' => $orden,
            'reportes' => $reportes,
            'resultados' => $resultados,
            'logoProderi' => $logoProderi,
            'imagenesPerTurno' => $imagenesPerTurno,
            'fechaGeneracion' => now()->format('d-m-Y'),
            'generador' => $generador,
            'datosOrden' => $datosOrden,
        ]);

        $pdf->setPaper('A4', 'portrait');

        return $pdf->download("reporte-vigilancia-{$orden->numero_orden}.pdf");
    }

    public function DownLoadInformeRevisionMedidores(OrdenJob $orden): Response
    {
        abort_unless($orden->tipo_servicio === TipoServicio::RevisionMedidores, 403);
        abort_unless(auth()->user()->can('informes'), 403);

        ini_set('memory_limit', '256M');

        $reporteId = request()->query('reporte');

        $orden->load(['personal', 'creadoPor', 'proceso']);

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

        $pdf = Pdf::loadView('pdf.reporte-revision-medidores', [
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

        return $pdf->download("reporte-revision-medidores-{$orden->numero_orden}.pdf");
    }

    public function descargarZipRevisionMedidores(Request $request): BinaryFileResponse
    {
        abort_unless($request->hasValidSignature(), 403);

        $path = $request->query('path');
        $absolutePath = storage_path('app/private/'.ltrim($path, '/'));

        abort_unless(file_exists($absolutePath), 404);

        return response()->download($absolutePath)->deleteFileAfterSend(true);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDatosReporteGestionRiesgo(reportes $reporte): array
    {
        return [
            'actividades' => $this->normalizeActividades($reporte->actividades ?? '[]'),
            'observaciones' => $reporte->observaciones ?: 'Sin observaciones registradas.',
            'video' => $reporte->video ?? null,
            'geo_referencia' => $reporte->geo_referencia ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDatosReporteAuditoriaCampo(reportes $reporte): array
    {
        $datos = $reporte->datos_servicio ?? [];

        if (($reporte->ordenJob?->tipo_auditoria_campo ?? null) === 'visita_calidad_medidores_digitales') {
            return [
                'es_medidores_digitales' => true,
                'fecha_visita_medidor' => $datos['fecha_visita_medidor'] ?? null,
                'tecnico_atendio_medidor' => $datos['tecnico_atendio_medidor'] ?? null,
                'nombre_atiende_medidor' => $datos['nombre_atiende_medidor'] ?? null,
                'informo_gratuidad_medidor' => (bool) ($datos['informo_gratuidad_medidor'] ?? false),
                'solicito_dinero_medidor' => (bool) ($datos['solicito_dinero_medidor'] ?? false),
                'gestion_conforme_medidor' => (bool) ($datos['gestion_conforme_medidor'] ?? false),
                'requiere_seguimiento_medidor' => (bool) ($datos['requiere_seguimiento_medidor'] ?? false),
                'hallazgos_medidor' => $datos['hallazgos_medidor'] ?? null,
                'autoriza' => (bool) ($datos['autoriza'] ?? false),
                'firma_usuario' => $datos['firma_usuario'] ?? null,
                'cedula_usuario' => $datos['cedula_usuario'] ?? null,
                'firma_auditor' => $datos['firma_auditor'] ?? null,
                'cedula_auditor' => $datos['cedula_auditor'] ?? null,
                'urls_evidencia' => $datos['urls_evidencia'] ?? [],
                'observaciones' => $reporte->observaciones ?: 'Sin observaciones registradas.',
                'geo_referencia' => $reporte->geo_referencia ?? null,
            ];
        }

        return [
            'es_medidores_digitales' => false,
            'fecha_hora_atencion' => $datos['fecha_hora_atencion'] ?? null,
            'ciudad' => $datos['ciudad'] ?? null,
            'celular_usuario' => $datos['celular_usuario'] ?? null,
            'comentario_usuario' => $datos['comentario_usuario'] ?? null,
            'comentario_tecnico' => $datos['comentario_tecnico'] ?? null,
            'nombre_atiende' => $datos['nombre_atiende'] ?? null,
            'vinculo_usuario' => $datos['vinculo_usuario'] ?? null,
            'celular_atiende' => $datos['celular_atiende'] ?? null,
            'recibio_visita' => (bool) ($datos['recibio_visita'] ?? false),
            'se_realizo_revision' => (bool) ($datos['se_realizo_revision'] ?? false),
            'fecha_ejecucion' => $datos['fecha_ejecucion'] ?? null,
            'tiempo_ejecucion' => $datos['tiempo_ejecucion'] ?? null,
            'descripcion_trabajo' => $datos['descripcion_trabajo'] ?? null,
            'se_detecto_fuga' => (bool) ($datos['se_detecto_fuga'] ?? false),
            'ppm' => $datos['ppm'] ?? null,
            'vol_porcentaje' => $datos['vol_porcentaje'] ?? null,
            'problema_persiste' => (bool) ($datos['problema_persiste'] ?? false),
            'evidencia_reparacion' => (bool) ($datos['evidencia_reparacion'] ?? false),
            'servicio_correcto' => (bool) ($datos['servicio_correcto'] ?? false),
            'se_usaron_materiales' => (bool) ($datos['se_usaron_materiales'] ?? false),
            'quien_suministro_materiales' => $datos['quien_suministro_materiales'] ?? null,
            'usuario_asumio_costo' => (bool) ($datos['usuario_asumio_costo'] ?? false),
            'valor_pagado' => $datos['valor_pagado'] ?? null,
            'medio_pago' => $datos['medio_pago'] ?? null,
            'entrego_factura' => (bool) ($datos['entrego_factura'] ?? false),
            'tecnico_dejo_documento' => $datos['tecnico_dejo_documento'] ?? null,
            'informaron_no_estaba' => (bool) ($datos['informaron_no_estaba'] ?? false),
            'autoriza' => (bool) ($datos['autoriza'] ?? false),
            'firma_usuario' => $datos['firma_usuario'] ?? null,
            'cedula_usuario' => $datos['cedula_usuario'] ?? null,
            'firma_auditor' => $datos['firma_auditor'] ?? null,
            'cedula_auditor' => $datos['cedula_auditor'] ?? null,
            'urls_evidencia' => $datos['urls_evidencia'] ?? [],
            'observaciones' => $reporte->observaciones ?: 'Sin observaciones registradas.',
            'geo_referencia' => $reporte->geo_referencia ?? null,
        ];
    }
}
