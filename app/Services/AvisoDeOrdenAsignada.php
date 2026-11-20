<?php

namespace App\Services;

use App\Models\OrdenJob;
use App\Models\User;
use App\Notifications\OrdenAsignadaPush;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Manda al celular del técnico o auditor el aviso de que se le asignó una orden.
 * Nunca interrumpe la asignación: si los avisos no están configurados o algo falla, solo se anota en el registro.
 */
class AvisoDeOrdenAsignada
{
    public static function disponible(): bool
    {
        return filled(config('webpush.vapid.public_key')) && filled(config('webpush.vapid.private_key'));
    }

    /** Número de la OT que reconoce el técnico: la del registro de suspensión y, si no hay, la de la orden. */
    public static function numeroDeOT(OrdenJob $orden): string
    {
        return (string) ($orden->surveyRegistro?->orden_trabajo ?: $orden->numero_orden);
    }

    public static function enviar(OrdenJob $orden): void
    {
        self::mandar(
            $orden->personal?->usuario,
            'OT '.self::numeroDeOT($orden),
            route('tecnico.ordenes.show', $orden, absolute: false),
            "orden-{$orden->id}",
            $orden->id,
        );
    }

    /**
     * Asignación de varias órdenes a la misma persona: un solo aviso con la cantidad, o con la OT si es una.
     *
     * @param  Collection<int, OrdenJob>  $ordenes
     */
    public static function enviarVarias(Collection $ordenes, ?User $usuario): void
    {
        if ($ordenes->count() === 1) {
            self::enviar($ordenes->first());

            return;
        }

        if ($ordenes->isEmpty()) {
            return;
        }

        self::mandar($usuario, "Tienes {$ordenes->count()} órdenes nuevas", route('tecnico.mis-ordenes', absolute: false), 'ordenes-nuevas', null);
    }

    private static function mandar(?User $usuario, string $texto, string $url, string $etiqueta, ?string $ordenId): void
    {
        if ($usuario === null || ! self::disponible()) {
            return;
        }

        try {
            $usuario->notify(new OrdenAsignadaPush($texto, $url, $etiqueta));
        } catch (Throwable $e) {
            Log::warning('No se pudo enviar el aviso de orden asignada al celular.', ['orden' => $ordenId, 'error' => $e->getMessage()]);
        }
    }
}
