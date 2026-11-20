<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Guarda y quita la suscripción del celular de la persona que activa los avisos de órdenes asignadas.
 */
class AvisoSuscripcionController extends Controller
{
    public function guardar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'endpoint' => ['required', 'url:https', 'max:500'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'contentEncoding' => ['nullable', 'in:aesgcm,aes128gcm'],
        ]);

        $request->user()->updatePushSubscription(
            $datos['endpoint'],
            $datos['keys']['p256dh'],
            $datos['keys']['auth'],
            $datos['contentEncoding'] ?? null,
        );

        return response()->json(['activado' => true]);
    }

    public function quitar(Request $request): JsonResponse
    {
        $datos = $request->validate(['endpoint' => ['required', 'url:https', 'max:500']]);

        $request->user()->deletePushSubscription($datos['endpoint']);

        return response()->json(['activado' => false]);
    }
}
