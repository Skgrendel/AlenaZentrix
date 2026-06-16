<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\InformeReporteRequest;
use App\Services\InformeService;
use Illuminate\Http\JsonResponse;

class InformeController extends Controller
{
    public function __construct(private readonly InformeService $service) {}

    /**
     * Devuelve el informe unificado (orden + reporte + auditoría) para consumo de Power BI.
     * Solo se aplican los filtros que vengan con valor en el body.
     */
    public function reporte(InformeReporteRequest $request): JsonResponse
    {
        $filters = $request->filters();
        $data = $this->service->unified($filters);

        return response()->json([
            'data' => $data,
            'total' => $data->count(),
            'filtros_aplicados' => $filters,
        ]);
    }
}
