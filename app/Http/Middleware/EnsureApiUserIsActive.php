<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rechaza (y revoca el token) cuando el usuario autenticado por Sanctum fue desactivado.
 */
class EnsureApiUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->estado) {
            $user->currentAccessToken()?->delete();

            return response()->json(['message' => 'Tu cuenta está desactivada.'], 403);
        }

        return $next($request);
    }
}
