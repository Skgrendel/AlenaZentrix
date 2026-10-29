<?php

namespace App\Http\Middleware;

use App\Enums\TipoServicio;
use App\Models\OrdenJob;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Responde 426 cuando una app móvil antigua (sin cabecera X-App-Version o con versión menor
 * a la mínima) intenta operar una orden de un subtipo que solo las versiones nuevas soportan.
 */
class RequireAppVersionParaSubtipo
{
    public function handle(Request $request, Closure $next): Response
    {
        $orden = $request->route('orden');

        if ($orden instanceof OrdenJob && self::esSubtipoNuevo($orden) && ! self::appSoportaSubtiposNuevos($request)) {
            return response()->json(['message' => 'Actualiza la aplicación para atender este tipo de visita.'], 426);
        }

        return $next($request);
    }

    public static function esSubtipoNuevo(OrdenJob $orden): bool
    {
        return $orden->tipo_servicio === TipoServicio::AuditoriaCampo
            && in_array($orden->tipo_auditoria_campo, config('api.subtipos_nuevos', []), true);
    }

    public static function appSoportaSubtiposNuevos(Request $request): bool
    {
        $version = trim((string) $request->header('X-App-Version', ''));

        if (! preg_match('/^v?\d+(\.\d+){0,2}/', $version)) {
            return false;
        }

        return version_compare(ltrim($version, 'v'), (string) config('api.min_version_subtipos_nuevos', '1.1.0'), '>=');
    }
}
