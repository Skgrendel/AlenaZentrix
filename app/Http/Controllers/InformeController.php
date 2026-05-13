<?php

namespace App\Http\Controllers;

use App\Enums\EstadoAuditoria;
use App\Enums\TipoServicio;
use App\Models\auditoria;
use App\Models\OrdenJob;
use App\Models\reportes as Reporte;
use Illuminate\Http\Request;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Controlador HTTP de descarga de informes en Excel.
 * El tipo de informe se determina por el rol del usuario autenticado para evitar acceso indebido.
 */
class InformeController extends Controller
{
    /**
     * Descarga el informe según el rol del usuario. Administrador puede elegir el tipo;
     * los demás roles tienen el tipo fijado por su perfil (Lector → reportes, Auditor → auditorías).
     */
    public function download(Request $request)
    {
        $user = auth()->user();

        if ($user->hasRole('Administrador')) {
            $tipo = $request->query('tipo', 'ordenes');
            abort_unless(in_array($tipo, ['ordenes', 'reportes', 'auditorias']), 400);
        } elseif ($user->hasRole('Lector')) {
            $tipo = 'reportes';
        } elseif ($user->hasRole('Auditor')) {
            $tipo = 'auditorias';
        } else {
            $tipo = 'ordenes';
        }

        $fechaInicio = $request->query('fecha_inicio');
        $fechaFin = $request->query('fecha_fin');
        $estado = $request->query('estado');
        $servicio = $request->query('servicio');
        $subTipo = $request->query('sub_tipo');

        [$headers, $rows, $filename] = match ($tipo) {
            'ordenes' => $this->dataOrdenes($fechaInicio, $fechaFin, $estado, $servicio, $subTipo),
            'reportes' => $this->dataReportes($user, $fechaInicio, $fechaFin, $estado, $servicio, $subTipo),
            'auditorias' => $this->dataAuditorias($fechaInicio, $fechaFin, $estado, $servicio, $subTipo),
        };

        return response()->streamDownload(function () use ($headers, $rows) {
            $writer = new Writer;
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValues($headers));
            foreach ($rows as $row) {
                $writer->addRow(Row::fromValues(array_values($row)));
            }
            $writer->close();
        }, $filename.'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Construye cabeceras, filas y nombre de archivo para el informe de órdenes.
     *
     * @return array{0: array<int, string>, 1: array<int, array<int, mixed>>, 2: string}
     */
    private function dataOrdenes(?string $fechaInicio, ?string $fechaFin, ?string $estado, ?string $servicio = null, ?string $subTipo = null): array
    {
        $ordenes = OrdenJob::query()
            ->with('personal')
            ->when($fechaInicio, fn ($q) => $q->whereDate('created_at', '>=', $fechaInicio))
            ->when($fechaFin, fn ($q) => $q->whereDate('created_at', '<=', $fechaFin))
            ->when($estado, fn ($q) => $q->where('estado', $estado))
            ->when($servicio, fn ($q) => $q->where('tipo_servicio', $servicio))
            ->when($subTipo && $servicio === TipoServicio::GestionLegal->value, fn ($q) => $q->where('tipo_gestion_legal', $subTipo))
            ->when($subTipo && $servicio === TipoServicio::Investigacion->value, fn ($q) => $q->where('tipo_investigacion', $subTipo))
            ->orderBy('created_at', 'desc')
            ->get();

        $headers = ['N° Orden', 'Servicio', 'Sub-tipo', 'Estado', 'Prioridad', 'Investigador', 'Cliente', 'Contrato', 'Dirección', 'Barrio', 'Medidor', 'Objetivo', 'N° Noticia Criminal', 'Fecha Asignación', 'Fecha Límite', 'Fecha Completado'];

        $rows = $ordenes->map(fn (OrdenJob $o) => [
            $o->numero_orden,
            $o->tipo_servicio->label(),
            $o->subtipoLabel(),
            $o->estado->label(),
            $o->prioridad->label(),
            $o->personal ? "{$o->personal->nombres} {$o->personal->apellidos}" : '',
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
        ])->all();

        return [$headers, $rows, 'ordenes-asignadas-'.now()->format('Y-m-d')];
    }

    /**
     * Construye cabeceras, filas y nombre de archivo para el informe de reportes del técnico.
     * Si el usuario no es Administrador, filtra solo las órdenes que le pertenecen.
     *
     * @return array{0: array<int, string>, 1: array<int, array<int, mixed>>, 2: string}
     */
    private function dataReportes($user, ?string $fechaInicio, ?string $fechaFin, ?string $estado, ?string $servicio = null, ?string $subTipo = null): array
    {
        $reportes = Reporte::query()
            ->with(['ordenJob.personal', 'comercio.tipoComercio', 'tipoImposibilidad', 'tipoRegulador'])
            ->when(! $user->hasRole('Administrador'), fn ($q) => $q->whereHas(
                'ordenJob', fn ($q2) => $q2->where('personals_id', $user->personals_id)
            ))
            ->when($fechaInicio, fn ($q) => $q->whereDate('created_at', '>=', $fechaInicio))
            ->when($fechaFin, fn ($q) => $q->whereDate('created_at', '<=', $fechaFin))
            ->when($estado, fn ($q) => $q->whereHas(
                'ordenJob', fn ($q2) => $q2->where('estado', $estado)
            ))
            ->when($servicio, fn ($q) => $q->whereHas(
                'ordenJob', fn ($q2) => $q2->where('tipo_servicio', $servicio)
            ))
            ->when($subTipo && $servicio === TipoServicio::GestionLegal->value, fn ($q) => $q->whereHas(
                'ordenJob', fn ($q2) => $q2->where('tipo_gestion_legal', $subTipo)
            ))
            ->when($subTipo && $servicio === TipoServicio::Investigacion->value, fn ($q) => $q->whereHas(
                'ordenJob', fn ($q2) => $q2->where('tipo_investigacion', $subTipo)
            ))
            ->orderBy('created_at', 'desc')
            ->get();

        $headers = ['N° Orden', 'Servicio', 'Sub-tipo', 'Estado Orden', 'Investigador', 'Dirección', 'Barrio', 'Cliente', 'Contrato', 'Lectura', 'N° Medidor', 'Comercio', 'Imposibilidad', 'Tipo Regulador', 'CAU', 'Actividades', 'Observaciones', 'Conclusión', 'Fecha Reporte'];

        $rows = $reportes->map(fn (Reporte $r) => [
            $r->ordenJob?->numero_orden ?? '',
            $r->ordenJob?->tipo_servicio?->label() ?? '',
            $r->ordenJob?->subtipoLabel() ?? '—',
            $r->ordenJob?->estado?->label() ?? '',
            $r->ordenJob?->personal ? "{$r->ordenJob->personal->nombres} {$r->ordenJob->personal->apellidos}" : '',
            $r->ordenJob?->direccion ?? '',
            $r->ordenJob?->barrio ?? '',
            $r->ordenJob?->cliente ?? '',
            $r->ordenJob?->contrato ?? '',
            $r->lectura ?? '',
            $r->numero_medidor ?? '',
            $r->comercio?->nombre_comercio ?? '',
            $r->tipoImposibilidad?->nombre ?? '',
            $r->tipoRegulador?->nombre ?? '',
            $r->cau ?? '',
            implode(' | ', array_column($r->actividades ?? [], 'actividades')),
            $r->observaciones ?? '',
            $r->conclusion ?? '',
            $r->created_at?->format('d/m/Y H:i') ?? '',
        ])->all();

        return [$headers, $rows, 'respuestas-tecnico-'.now()->format('Y-m-d')];
    }

    /**
     * Construye cabeceras, filas y nombre de archivo para el informe de auditorías.
     *
     * @return array{0: array<int, string>, 1: array<int, array<int, mixed>>, 2: string}
     */
    private function dataAuditorias(?string $fechaInicio, ?string $fechaFin, ?string $estado, ?string $servicio = null, ?string $subTipo = null): array
    {
        $auditorias = auditoria::query()
            ->with(['reporte.ordenJob.personal'])
            ->when($fechaInicio, fn ($q) => $q->whereDate('fecha_auditoria', '>=', $fechaInicio))
            ->when($fechaFin, fn ($q) => $q->whereDate('fecha_auditoria', '<=', $fechaFin))
            ->when($estado, fn ($q) => $q->where('estado', $estado))
            ->orderBy('fecha_auditoria', 'desc')
            ->get();

        $headers = ['N° Orden', 'Cliente', 'Barrio', 'Técnico', 'Estado Auditoría', 'Observaciones', 'Fecha Completado Orden', 'Fecha Auditoría'];

        $rows = $auditorias->map(fn (auditoria $a) => [
            $a->reporte?->ordenJob?->numero_orden ?? '',
            $a->reporte?->ordenJob?->cliente ?? '',
            $a->reporte?->ordenJob?->barrio ?? '',
            $a->reporte?->ordenJob?->personal
                ? "{$a->reporte->ordenJob->personal->nombres} {$a->reporte->ordenJob->personal->apellidos}"
                : '',
            $a->estado instanceof EstadoAuditoria ? $a->estado->label() : ($a->estado ?? ''),
            $a->observaciones ?? '',
            $a->reporte?->ordenJob?->fecha_completado?->format('d/m/Y H:i') ?? '',
            $a->fecha_auditoria?->format('d/m/Y H:i') ?? '',
        ])->all();

        return [$headers, $rows, 'respuestas-auditor-'.now()->format('Y-m-d')];
    }
}
