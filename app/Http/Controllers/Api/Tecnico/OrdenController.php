<?php

namespace App\Http\Controllers\Api\Tecnico;

use App\Enums\EstadoOrden;
use App\Enums\TipoServicio;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\OrdenResource;
use App\Mail\OrdenIniciada;
use App\Models\OrdenJob;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OrdenController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $ordenes = OrdenJob::query()
            ->whereIn('estado', [
                EstadoOrden::Pendiente->value,
                EstadoOrden::Asignada->value,
                EstadoOrden::EnProgreso->value,
                EstadoOrden::EnRevision->value,
                EstadoOrden::Rechazada->value,
            ])
            ->where(function ($q) use ($user) {
                $q->where('personals_id', $user->personals_id)
                    ->orWhere('tipo_servicio', TipoServicio::Vigilancia->value);
            })
            ->orderByRaw("CASE WHEN estado = 'en_revision' THEN 1 ELSE 0 END DESC")
            ->orderBy('created_at', 'desc')
            ->get();

        return OrdenResource::collection($ordenes);
    }

    public function show(OrdenJob $orden): OrdenResource
    {
        return new OrdenResource($orden->load('personal'));
    }

    public function iniciar(Request $request, OrdenJob $orden): JsonResponse
    {
        if ($orden->estado !== EstadoOrden::Asignada) {
            return response()->json(['message' => 'La orden no está en estado Asignada.'], 422);
        }

        if ($orden->tipo_servicio === TipoServicio::Vigilancia) {
            return response()->json(['message' => 'Las órdenes de Vigilancia no requieren inicio manual.'], 422);
        }

        $orden->update(['estado' => EstadoOrden::EnProgreso]);

        $this->notificarCreador($orden, $request->user());

        return response()->json([
            'message' => 'Orden iniciada correctamente.',
            'orden' => new OrdenResource($orden),
        ]);
    }

    public function cerrarVigilancia(OrdenJob $orden): JsonResponse
    {
        if ($orden->tipo_servicio !== TipoServicio::Vigilancia) {
            return response()->json(['message' => 'Esta acción solo aplica a órdenes de Vigilancia.'], 422);
        }

        if ($orden->estado !== EstadoOrden::EnProgreso) {
            return response()->json(['message' => 'La orden no está En Progreso.'], 422);
        }

        $orden->update(['estado' => EstadoOrden::EnRevision]);

        return response()->json([
            'message' => 'Vigilancia cerrada correctamente.',
            'orden' => new OrdenResource($orden),
        ]);
    }

    private function notificarCreador(OrdenJob $orden, User $user): void
    {
        $email = $orden->creadoPor?->email;
        $tecnico = $orden->personal;

        if (! $email || ! $tecnico) {
            return;
        }

        try {
            Mail::to($email)->send(new OrdenIniciada($orden, $tecnico));
        } catch (\Exception $e) {
            Log::error('No se pudo enviar notificación de orden iniciada.', [
                'orden' => $orden->numero_orden,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
