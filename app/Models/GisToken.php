<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

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

    public static function getActiveToken()
    {
        $token = self::where('activo', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', Carbon::now());
            })
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$token) {
            Log::warning('No hay token válido en BD (todos expirados o ausentes)');
            return config('app.gis_api_token');
        }

        if ($token->expires_at && $token->expires_at->isPast()) {
            Log::warning('Token en BD está expirado: ' . $token->expires_at->toDateTimeString());
            return null;
        }

        Log::info('Token válido en BD, expira en ' . $token->expires_at->diffInMinutes(Carbon::now()) . ' minutos');
        return $token->token;
    }

    public static function isTokenExpiringSoon()
    {
        $token = self::where('activo', true)->first();

        if (!$token) {
            return true;
        }

        if (!$token->expires_at) {
            return false;
        }

        return $token->expires_at->diffInMinutes(Carbon::now()) <= 5;
    }
}
