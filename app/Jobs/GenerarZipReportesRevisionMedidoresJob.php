<?php

namespace App\Jobs;

use App\Models\OrdenJob;
use App\Models\TipoAnomalia;
use App\Models\User;
use App\Notifications\ZipReportesListoNotification;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use ZipArchive;

class GenerarZipReportesRevisionMedidoresJob implements ShouldQueue
{
    use Queueable;

    /** @param array<int, string> $ordenIds */
    public function __construct(
        public array $ordenIds,
        private string $userId,
    ) {}

    public function handle(): void
    {
        ini_set('memory_limit', '256M');

        $zipPath = 'zips/revision-medidores-'.now()->format('YmdHis').'-'.uniqid().'.zip';
        $absoluteZipPath = storage_path('app/private/'.$zipPath);

        $dir = dirname($absoluteZipPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $zip = new ZipArchive;
        $zip->open($absoluteZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $ordenes = OrdenJob::whereIn('id', $this->ordenIds)
            ->with(['personal', 'creadoPor', 'reportes'])
            ->get();

        $logoProderi = $this->fileToBase64(public_path('assets/image/LOGO PRODERI CON SLOGAN.png'), 'image/png');
        $objetivos = $this->resolveObjetivos('revision_medidores');

        foreach ($ordenes as $orden) {
            $reporte = $orden->reportes->sortByDesc('created_at')->first();
            if (! $reporte) {
                continue;
            }

            $anomalias = collect($reporte->anomalia ?? [])
                ->map(fn (string|int $id) => TipoAnomalia::find($id)?->nombre)
                ->filter()
                ->implode(', ');

            $imagenes = $this->resolveImagenes($reporte->imagenes ?? []);

            $firma = [
                'nombre' => 'Sistema — Descarga Masiva',
                'cargo' => null,
                'email' => '—',
                'ip' => '—',
                'agente' => 'Queue Worker',
                'fecha' => now()->format('d/m/Y H:i:s'),
            ];

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

            $zip->addFromString(
                "reporte-revision-medidores-{$orden->numero_orden}.pdf",
                $pdf->output()
            );
        }

        $zip->close();

        $signedUrl = URL::temporarySignedRoute(
            'operaciones.revision-medidores.descargar-zip',
            now()->addMinutes(60),
            ['path' => $zipPath]
        );

        $user = User::find($this->userId);
        $user?->notify(new ZipReportesListoNotification($signedUrl, count($this->ordenIds)));
    }

    /** @return array<int, string> */
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

    /** @return array<int, array{numero: string, items: array<int, string>}> */
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
