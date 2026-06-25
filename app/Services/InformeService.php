<?php

namespace App\Services;

use App\Enums\EstadoAuditoria;
use App\Enums\TipoServicio;
use App\Models\auditoria;
use App\Models\OrdenJob;
use Illuminate\Support\Collection;

class InformeService
{
    /**
     * Ejecuta la query unificada orden → reporte → auditoría con los filtros dados.
     *
     * @param  array<string, string>  $filters  Solo los filtros con valor (sin claves vacías)
     * @return Collection<int, array<string, mixed>>
     */
    public function unified(array $filters): Collection
    {
        $servicio = $filters['servicio'] ?? null;

        return OrdenJob::query()
            ->with([
                'personal',
                'proceso',
                'reportes' => fn ($q) => $q->latest()->with([
                    'auditoria',
                    'comercio',
                    'tipoImposibilidad',
                    'tipoRegulador',
                ]),
            ])
            ->when($filters['fecha_inicio'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['fecha_fin'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->when($filters['estado'] ?? null, fn ($q, $v) => $q->where('estado', $v))
            ->when($servicio, fn ($q, $v) => $q->where('tipo_servicio', $v))
            ->when($filters['proceso'] ?? null, fn ($q, $v) => $q->where('proceso_id', $v))
            ->when(
                isset($filters['sub_tipo']) && $servicio === TipoServicio::GestionLegal->value,
                fn ($q) => $q->where('tipo_gestion_legal', $filters['sub_tipo'])
            )
            ->when(
                isset($filters['sub_tipo']) && $servicio === TipoServicio::Investigacion->value,
                fn ($q) => $q->where('tipo_investigacion', $filters['sub_tipo'])
            )
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (OrdenJob $o) => $this->formatRow($o));
    }

    /** @return array<string, mixed> */
    private function formatRow(OrdenJob $o): array
    {
        $reporte = $o->reportes->first();
        $auditoria = $reporte?->auditoria;

        return [
            // — Orden —
            'numero_orden' => $o->numero_orden,
            'proceso' => $o->proceso?->nombre,
            'servicio' => $o->tipo_servicio->label(),
            'sub_tipo' => $o->subtipoLabel(),
            'estado' => $o->estado->label(),
            'prioridad' => $o->prioridad->label(),
            'investigador' => $o->personal
                ? "{$o->personal->nombres} {$o->personal->apellidos}"
                : null,
            'cliente' => $o->cliente,
            'contrato' => $o->contrato,
            'direccion' => $o->direccion,
            'barrio' => $o->barrio,
            'medidor' => $o->medidor,
            'objetivo' => $o->objetivo,
            'noticia_criminal' => $o->numero_noticia_criminal,
            'fecha_asignacion' => $o->fecha_asignacion?->format('Y-m-d H:i:s'),
            'fecha_limite' => $o->fecha_limite?->format('Y-m-d'),
            'fecha_completado' => $o->fecha_completado?->format('Y-m-d H:i:s'),
            // — Reporte del técnico —
            'tiene_reporte' => $reporte !== null,
            'lectura' => $reporte?->lectura,
            'numero_medidor_rep' => $reporte?->numero_medidor,
            'comercio' => $reporte?->comercio?->nombre_comercio,
            'imposibilidad' => $reporte?->tipoImposibilidad?->nombre,
            'tipo_regulador' => $reporte?->tipoRegulador?->nombre,
            'actividades' => $reporte
                ? implode(' | ', array_column($reporte->actividades ?? [], 'actividades'))
                : null,
            'observaciones_rep' => $reporte?->observaciones,
            'conclusion' => $reporte?->conclusion,
            'fecha_reporte' => $reporte?->created_at?->format('Y-m-d H:i:s'),
            // — Auditoría —
            'estado_auditoria' => $auditoria instanceof auditoria
                ? ($auditoria->estado instanceof EstadoAuditoria ? $auditoria->estado->label() : $auditoria->estado)
                : null,
            'observaciones_aud' => $auditoria?->observaciones,
            'fecha_auditoria' => $auditoria?->fecha_auditoria?->format('Y-m-d H:i:s'),
        ];
    }
}
