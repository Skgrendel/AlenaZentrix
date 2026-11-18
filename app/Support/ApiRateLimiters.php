<?php

namespace App\Support;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class ApiRateLimiters
{
    public static function register(): void
    {
        RateLimiter::for('api-usuario', fn (Request $request): Limit => self::limite(120, $request));
        RateLimiter::for('api-upload', fn (Request $request): Limit => self::limite(30, $request));
        RateLimiter::for('api-archivos', fn (Request $request): Limit => self::limite(120, $request));
    }

    private static function limite(int $porMinuto, Request $request): Limit
    {
        return Limit::perMinute($porMinuto)
            ->by($request->user()?->getAuthIdentifier() ?: $request->ip())
            ->response(fn () => response()->json([
                'message' => 'Demasiadas solicitudes. Intenta de nuevo en unos instantes.',
            ], 429));
    }
}
