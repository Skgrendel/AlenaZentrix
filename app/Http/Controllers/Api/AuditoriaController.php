<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AuditoriaGuardarRequest;
use App\Http\Resources\Api\AuditoriaResource;
use App\Models\auditoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditoriaController extends Controller
{
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
