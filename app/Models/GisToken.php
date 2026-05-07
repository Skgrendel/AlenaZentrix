<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Token de autenticación para la API GIS de cartografía. Permite rotación de tokens sin despliegue.
 */
class GisToken extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'token',
        'descripcion',
        'activo',
        'expires_at',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'expires_at' => 'datetime',
    ];

    /**
     * Retorna el token activo y vigente más reciente.
     * Si no hay ninguno en BD, cae al valor de configuración `app.gis_api_token` como último recurso.
     *
     * @return string|null Token válido, o null si el único disponible está expirado.
     */
    public static function getActiveToken()
    {
        $token = self::where('activo', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', Carbon::now());
            })
            ->orderBy('created_at', 'desc')
            ->first();

        if (! $token) {
            Log::warning('No hay token válido en BD (todos expirados o ausentes)');

            return config('app.gis_api_token');
        }

        if ($token->expires_at && $token->expires_at->isPast()) {
            Log::warning('Token en BD está expirado: '.$token->expires_at->toDateTimeString());

            return null;
        }

        Log::info('Token válido en BD, expira en '.$token->expires_at->diffInMinutes(Carbon::now()).' minutos');

        return $token->token;
    }

    /**
     * Indica si el token activo vence en los próximos 5 minutos o si no existe ninguno.
     * Se usa para alertar sobre la necesidad de renovación antes de que expire.
     */
    public static function isTokenExpiringSoon()
    {
        $token = self::where('activo', true)->first();

        if (! $token) {
            return true;
        }

        if (! $token->expires_at) {
            return false;
        }

        return $token->expires_at->diffInMinutes(Carbon::now()) <= 5;
    }
}
