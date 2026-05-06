<?php

use App\Enums\EstadoOrden;
use App\Livewire\Admin\Configuracion;
use App\Livewire\Admin\ManageUsers;
use App\Livewire\Auditor\AuditoriasIndex;
use App\Livewire\Coordinador\OrdenesCompletadasIndex;
use App\Livewire\Coordinador\OrdenesTrabajoIndex;
use App\Livewire\Coordinador\OrdenJobShow;
use App\Livewire\Coordinador\ReporteRevisar;
use App\Livewire\Tecnico\MisOrdenesIndex;
use App\Livewire\Tecnico\ReporteCreate;
use App\Livewire\Tecnico\ReporteEdit;
use App\Models\OrdenJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect('/login');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::middleware('role:Administrador|Coordinador|Lector')
        ->get('operaciones/foto', function (Request $request) {
            $path = $request->query('path', '');

            if (! str_starts_with($path, 'ordenes/') && ! str_starts_with($path, 'reportes/')) {
                abort(403);
            }

            $disk = Storage::disk('s3');

            if (! $disk->exists($path)) {
                abort(404);
            }

            return response()->stream(
                fn () => fpassthru($disk->readStream($path)),
                200,
                ['Content-Type' => $disk->mimeType($path), 'Cache-Control' => 'max-age=3600, private'],
            );
        })->name('operaciones.foto');

    Route::middleware('role:Administrador|Coordinador')->prefix('operaciones')->name('operaciones.')->group(function () {
        Route::get('ordenes-trabajo', OrdenesTrabajoIndex::class)->name('ordenes-trabajo');
        Route::get('ordenes-trabajo/{orden}', OrdenJobShow::class)->name('ordenes-trabajo.show');
        Route::get('ordenes-trabajo/{orden}/revisar', ReporteRevisar::class)->name('ordenes-trabajo.revisar');
        Route::get('ordenes-completadas', OrdenesCompletadasIndex::class)->name('ordenes-completadas');

        Route::get('exportar-ordenes', function () {
            $ordenes = OrdenJob::query()
                ->with('personal')
                ->orderBy('created_at', 'desc')
                ->get();

            $filename = 'ordenes-trabajo-'.now()->format('Y-m-d').'.csv';

            return response()->streamDownload(function () use ($ordenes) {
                $handle = fopen('php://output', 'w');
                fwrite($handle, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
                fputcsv($handle, ['N° Orden', 'Estado', 'Cliente', 'Contrato', 'Dirección', 'Barrio', 'Medidor', 'Prioridad', 'Técnico', 'Fecha Asignación', 'Fecha Límite', 'Fecha Completado']);

                foreach ($ordenes as $orden) {
                    fputcsv($handle, [
                        $orden->numero_orden,
                        $orden->estado->label(),
                        $orden->cliente,
                        $orden->contrato,
                        $orden->direccion,
                        $orden->barrio,
                        $orden->medidor,
                        $orden->prioridad->label(),
                        $orden->personal ? "{$orden->personal->nombres} {$orden->personal->apellidos}" : '',
                        $orden->fecha_asignacion?->format('d/m/Y H:i'),
                        $orden->fecha_limite?->format('d/m/Y H:i'),
                        $orden->fecha_completado?->format('d/m/Y H:i'),
                    ]);
                }

                fclose($handle);
            }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
        })->name('exportar-ordenes');

        Route::get('exportar-completadas', function () {
            $ordenes = OrdenJob::query()
                ->with(['personal', 'reportes' => fn ($q) => $q->with(['comercio.tipoComercio', 'tipoImposibilidad', 'tipoRegulador'])->latest()])
                ->where('estado', EstadoOrden::Completada)
                ->orderBy('fecha_completado', 'desc')
                ->get();

            $filename = 'ordenes-completadas-'.now()->format('Y-m-d').'.csv';

            return response()->streamDownload(function () use ($ordenes) {
                $handle = fopen('php://output', 'w');
                fwrite($handle, "\xEF\xBB\xBF");
                fputcsv($handle, [
                    'N° Orden', 'Cliente', 'Contrato', 'Dirección', 'Barrio', 'Medidor',
                    'Prioridad', 'Técnico', 'Lectura', 'N° Medidor Reporte', 'Comercio',
                    'Tipo Comercio', 'Imposibilidad', 'Regulador', 'CAU', 'Observaciones',
                    'Fecha Completado',
                ]);

                foreach ($ordenes as $orden) {
                    $reporte = $orden->reportes->first();
                    fputcsv($handle, [
                        $orden->numero_orden,
                        $orden->cliente,
                        $orden->contrato,
                        $orden->direccion,
                        $orden->barrio,
                        $orden->medidor,
                        $orden->prioridad->label(),
                        $orden->personal ? "{$orden->personal->nombres} {$orden->personal->apellidos}" : '',
                        $reporte?->lectura ?? '',
                        $reporte?->numero_medidor ?? '',
                        $reporte?->comercio?->nombre_comercio ?? '',
                        $reporte?->comercio?->tipoComercio?->nombre ?? '',
                        $reporte?->tipoImposibilidad?->nombre ?? '',
                        $reporte?->tipoRegulador?->nombre ?? '',
                        $reporte?->cau ?? '',
                        $reporte?->observaciones ?? '',
                        $orden->fecha_completado?->format('d/m/Y H:i'),
                    ]);
                }

                fclose($handle);
            }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
        })->name('exportar-completadas');
    });

    Route::middleware('role:Administrador|Lector')->prefix('tecnico')->name('tecnico.')->group(function () {
        Route::get('mis-ordenes', MisOrdenesIndex::class)->name('mis-ordenes');
        Route::get('ordenes/{orden}/reportar', ReporteCreate::class)->name('ordenes.reportar');
        Route::get('ordenes/{orden}/reporte/editar', ReporteEdit::class)->name('ordenes.reporte.editar');

        Route::get('exportar-mis-ordenes', function () {
            $user = auth()->user();
            $query = OrdenJob::query()->with('personal');

            if (! $user->hasRole('Administrador')) {
                $query->where('personals_id', $user->personals_id);
            }

            $ordenes = $query->orderBy('created_at', 'desc')->get();
            $filename = 'mis-ordenes-'.now()->format('Y-m-d').'.csv';

            return response()->streamDownload(function () use ($ordenes) {
                $handle = fopen('php://output', 'w');
                fwrite($handle, "\xEF\xBB\xBF");
                fputcsv($handle, ['N° Orden', 'Estado', 'Cliente', 'Contrato', 'Dirección', 'Barrio', 'Prioridad', 'Fecha Límite']);

                foreach ($ordenes as $orden) {
                    fputcsv($handle, [
                        $orden->numero_orden,
                        $orden->estado->label(),
                        $orden->cliente,
                        $orden->contrato,
                        $orden->direccion,
                        $orden->barrio,
                        $orden->prioridad->label(),
                        $orden->fecha_limite?->format('d/m/Y H:i'),
                    ]);
                }

                fclose($handle);
            }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
        })->name('exportar-mis-ordenes');
    });

    Route::middleware('role:Administrador|Auditor')->prefix('auditor')->name('auditor.')->group(function () {
        Route::get('auditorias', AuditoriasIndex::class)->name('auditorias');
    });

    Route::middleware('role:Administrador')->prefix('admin')->name('admin.')->group(function () {
        Route::get('usuarios', ManageUsers::class)->name('users');
        Route::get('configuracion', Configuracion::class)->name('configuracion');
    });
});

require __DIR__.'/settings.php';
