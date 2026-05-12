<?php

namespace App\Http\Controllers;

use App\Enums\TipoServicio;
use App\Models\OrdenJob;
use App\Models\TipoAnomalia;
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
            'nombre' => $firmante->fullname(),
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
