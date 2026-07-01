<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Convencion;
use App\Models\MarcaMedidor;
use App\Models\MarcaRegulador;
use App\Models\ResultadoReporte;
use App\Models\TipoAnomalia;
use App\Models\TipoComercio;
use App\Models\TipoImposibilidad;
use App\Models\TipoNotificacionAlerta;
use App\Models\TipoRegulador;
use Illuminate\Http\JsonResponse;

class CatalogoController extends Controller
{
    public function tiposComercio(): JsonResponse
    {
        return response()->json(
            TipoComercio::where('activo', true)->orderBy('nombre')->get(['id', 'nombre'])
        );
    }

    public function marcasMedidor(): JsonResponse
    {
        return response()->json(
            MarcaMedidor::where('activo', true)->orderBy('nombre')->get(['id', 'nombre'])
        );
    }

    public function marcasRegulador(): JsonResponse
    {
        return response()->json(
            MarcaRegulador::where('activo', true)->orderBy('nombre')->get(['id', 'nombre'])
        );
    }

    public function tiposRegulador(): JsonResponse
    {
        return response()->json(
            TipoRegulador::where('activo', true)->orderBy('nombre')->get(['id', 'nombre'])
        );
    }

    public function tiposAnomalia(): JsonResponse
    {
        return response()->json(
            TipoAnomalia::where('activo', true)->orderBy('nombre')->get(['id', 'nombre'])
        );
    }

    public function tiposImposibilidad(): JsonResponse
    {
        return response()->json(
            TipoImposibilidad::where('activo', true)->orderBy('nombre')->get(['id', 'nombre'])
        );
    }

    public function tiposNotificacionAlerta(): JsonResponse
    {
        return response()->json(
            TipoNotificacionAlerta::where('activo', true)->orderBy('nombre')->get(['id', 'nombre'])
        );
    }

    public function convenciones(): JsonResponse
    {
        return response()->json(
            Convencion::where('activo', true)->orderBy('nombre')->get(['id', 'nombre'])
        );
    }

    public function resultadosReporte(): JsonResponse
    {
        return response()->json(
            ResultadoReporte::where('activo', true)->orderBy('nombre')->get(['id', 'nombre'])
        );
    }
}
