<?php

namespace App\Http\Controllers\Api\Tecnico;

use App\Enums\EstadoOrden;
use App\Enums\TipoServicio;
use App\Http\Controllers\Controller;
use App\Http\Middleware\RequireAppVersionParaSubtipo;
use App\Http\Resources\Api\OrdenResource;
use App\Mail\OrdenIniciada;
use App\Models\OrdenJob;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OrdenController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['desde' => ['nullable', 'date_format:Y-m-d']]);

        $user = $request->user();

        if (blank($user->personals_id)) {
            return OrdenResource::collection(collect());
        }

        $desde = $request->filled('desde')
            ? Carbon::createFromFormat('Y-m-d', (string) $request->input('desde'))->startOfDay()
            : now()->subDays((int) config('api.dias_completadas', 90))->startOfDay();

        $ordenes = OrdenJob::query()
            ->whereIn('estado', [
                EstadoOrden::Pendiente->value,
                EstadoOrden::Asignada->value,
                EstadoOrden::EnProgreso->value,
                EstadoOrden::EnRevision->value,
                EstadoOrden::Rechazada->value,
                EstadoOrden::Completada->value,
            ])
            ->where(function ($q) use ($desde) {
                $q->where('estado', '!=', EstadoOrden::Completada->value)
                    ->orWhereRaw('COALESCE(fecha_completado, updated_at) >= ?', [$desde]);
            })
            ->where(function ($q) use ($user) {
                $q->where('personals_id', $user->personals_id)
                    ->orWhere('tipo_servicio', TipoServicio::Vigilancia->value);
            })
            ->when(
                ! RequireAppVersionParaSubtipo::appSoportaSubtiposNuevos($request),
                fn ($q) => $q->whereNot(function ($q) {
                    $q->where('tipo_servicio', TipoServicio::AuditoriaCampo->value)
                        ->whereIn('tipo_auditoria_campo', config('api.subtipos_nuevos', []));
                })
            )
            ->withCount('reportes')
            ->with(['reportes' => fn ($q) => $q
                ->where('requiere_correccion', true)
                ->select(['id', 'orden_job_id', 'datos_servicio', 'comentarios', 'updated_at', 'requiere_correccion']),
            ])
            ->orderByRaw("CASE WHEN estado = 'en_revision' THEN 1 ELSE 0 END DESC")
            ->orderBy('created_at', 'desc')
            ->get();

        return OrdenResource::collection($ordenes);
    }

    public function show(Request $request, OrdenJob $orden): JsonResponse|OrdenResource
    {
        $user = $request->user();

        if (! $this->esDueno($user, $orden) && ! $user->can('ordenes.ver')) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        return new OrdenResource($orden->load('personal')->loadCount('reportes'));
    }

    public function iniciar(Request $request, OrdenJob $orden): JsonResponse
    {
        $user = $request->user();

        if (! $this->esDueno($user, $orden)) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        if ($orden->tipo_servicio === TipoServicio::Vigilancia) {
            return response()->json(['message' => 'Las órdenes de Vigilancia no requieren inicio manual.'], 422);
        }

        if ($orden->estado === EstadoOrden::EnProgreso) {
            return response()->json([
                'message' => 'La orden ya estaba iniciada.',
                'orden' => new OrdenResource($orden->loadCount('reportes')),
            ]);
        }

        if ($orden->estado !== EstadoOrden::Asignada) {
            return response()->json(['message' => 'La orden no está en estado Asignada.'], 422);
        }

        $orden->update(['estado' => EstadoOrden::EnProgreso]);

        $this->notificarCreador($orden);

        return response()->json([
            'message' => 'Orden iniciada correctamente.',
            'orden' => new OrdenResource($orden->loadCount('reportes')),
        ]);
    }

    public function cerrarVigilancia(Request $request, OrdenJob $orden): JsonResponse
    {
        if (! $this->esDueno($request->user(), $orden)) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        if ($orden->tipo_servicio !== TipoServicio::Vigilancia) {
            return response()->json(['message' => 'Esta acción solo aplica a órdenes de Vigilancia.'], 422);
        }

        if ($orden->estado === EstadoOrden::EnRevision) {
            return response()->json([
                'message' => 'La vigilancia ya estaba cerrada.',
                'orden' => new OrdenResource($orden->loadCount('reportes')),
            ]);
        }

        if ($orden->estado !== EstadoOrden::EnProgreso) {
            return response()->json(['message' => 'La orden no está En Progreso.'], 422);
        }

        $orden->update(['estado' => EstadoOrden::EnRevision]);

        return response()->json([
            'message' => 'Vigilancia cerrada correctamente.',
            'orden' => new OrdenResource($orden->loadCount('reportes')),
        ]);
    }

    /**
     * Dueño de la orden: el técnico asignado; en Vigilancia (compartida en la empresa)
     * cualquier usuario con personal vinculado.
     */
    private function esDueno(User $user, OrdenJob $orden): bool
    {
        if (blank($user->personals_id)) {
            return false;
        }

        return $orden->tipo_servicio === TipoServicio::Vigilancia
            || $user->personals_id === $orden->personals_id;
    }

    private function notificarCreador(OrdenJob $orden): void
    {
        $email = $orden->creadoPor?->email;
        $tecnico = $orden->personal;

        if (! $email || ! $tecnico) {
            return;
        }

        try {
            Mail::to($email)->send(new OrdenIniciada($orden, $tecnico));
        } catch (\Throwable $e) {
            Log::error('No se pudo enviar notificación de orden iniciada.', [
                'orden' => $orden->numero_orden,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
