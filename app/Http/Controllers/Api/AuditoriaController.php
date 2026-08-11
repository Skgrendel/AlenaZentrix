<?php

namespace App\Http\Controllers\Api;

use App\Enums\EstadoAuditoria;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AuditoriaGuardarRequest;
use App\Http\Resources\Api\AuditoriaResource;
use App\Models\auditoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class AuditoriaController extends Controller
{
    /**
     * Lista las auditorías visibles para el usuario autenticado: todas las de sus
     * servicios auditables si tiene el permiso `auditorias.ver` (rol Auditor), o
     * solo las de sus propias órdenes si es el técnico dueño (mismo criterio que show/guardar).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'estado' => ['nullable', Rule::in(array_column(EstadoAuditoria::cases(), 'value'))],
            'tipo_servicio' => ['nullable', 'string'],
        ]);

        $user = $request->user();

        $auditorias = auditoria::query()
            ->with([
                'reporte.ordenJob',
                'reporte.comercio.tipoComercio',
                'tipoAnomalia',
                'auditadoPor',
            ])
            ->when(
                $user->can('auditorias.ver'),
                function ($query) use ($user) {
                    $serviciosAuditables = $user->serviciosAuditables()->pluck('tipo_servicio')->toArray();

                    $query->whereHas('reporte.ordenJob', fn ($q) => $q->whereIn('tipo_servicio', $serviciosAuditables));
                },
                fn ($query) => $query->whereHas('reporte.ordenJob', fn ($q) => $q->where('personals_id', $user->personals_id))
            )
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->string('estado')))
            ->when($request->filled('tipo_servicio'), fn ($q) => $q->whereHas(
                'reporte.ordenJob',
                fn ($q) => $q->where('tipo_servicio', $request->string('tipo_servicio'))
            ))
            ->orderByDesc('created_at')
            ->get();

        return AuditoriaResource::collection($auditorias);
    }

    public function show(Request $request, auditoria $auditoria): JsonResponse|AuditoriaResource
    {
        $user = $request->user();

        if (
            ! $user->can('auditorias.ver')
            && $auditoria->reporte?->ordenJob?->personals_id !== $user->personals_id
        ) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $auditoria->load([
            'reporte.ordenJob',
            'reporte.comercio.tipoComercio',
            'reporte.personal',
            'reporte.ubicacion',
            'tipoAnomalia',
            'auditadoPor',
        ]);

        return new AuditoriaResource($auditoria);
    }

    public function guardar(AuditoriaGuardarRequest $request, auditoria $auditoria): JsonResponse
    {
        $data = $request->validated();

        $auditoria->update([
            'evidencias' => $data['evidencias'],
            'estado' => $data['resultado'],
            'observaciones' => $data['observaciones'] ?? null,
            'tipo_anomalia_id' => $data['tipo_anomalia_id'] ?? null,
            'metros_cubicos_recuperados' => $data['metros_cubicos_recuperados'] ?? null,
            'auditado_por_id' => $request->user()->id,
            'fecha_auditoria' => now(),
        ]);

        if (! empty($data['contrato'])) {
            $auditoria->reporte?->ordenJob?->update(['contrato' => $data['contrato']]);
        }

        $auditoria->refresh()->load([
            'reporte.ordenJob',
            'reporte.comercio.tipoComercio',
            'reporte.personal',
            'reporte.ubicacion',
            'tipoAnomalia',
            'auditadoPor',
        ]);

        return response()->json([
            'message' => 'Auditoría registrada correctamente.',
            'auditoria' => new AuditoriaResource($auditoria),
        ]);
    }
}
