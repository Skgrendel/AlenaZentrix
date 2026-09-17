<?php

namespace App\Http\Controllers\Api\Tecnico;

use App\Enums\EstadoOrden;
use App\Enums\TipoServicio;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\ReporteResource;
use App\Mail\IncidenciaDetectada;
use App\Mail\ReporteEnRevision;
use App\Models\comercio;
use App\Models\CorreoAlerta;
use App\Models\OrdenJob;
use App\Models\reportes;
use App\Models\TipoAnomalia;
use App\Models\ubicacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Flysystem\UnableToCheckExistence;

class ReporteController extends Controller
{
    public function show(Request $request, OrdenJob $orden): JsonResponse|ReporteResource
    {
        $user = $request->user();

        if (
            $user->personals_id !== $orden->personals_id &&
            ! $user->can('ordenes.ver')
        ) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $reporte = $orden->reportes()->latest()->firstOrFail();

        return new ReporteResource($reporte->load('ubicacion'));
    }

    public function store(Request $request, OrdenJob $orden): JsonResponse
    {
        $user = $request->user();
        $esVigilancia = $orden->tipo_servicio === TipoServicio::Vigilancia;

        if ($esVigilancia) {
            if (! $user->personals_id && ! $user->can('ordenes.ver')) {
                return response()->json(['message' => 'No autorizado.'], 403);
            }
        } elseif (
            $user->personals_id !== $orden->personals_id &&
            ! $user->can('ordenes.ver')
        ) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        // Servicios de un solo reporte salen de "en progreso" en cuanto el reporte se guarda. Si
        // la app reenvía el mismo reporte porque perdió la respuesta 201 por falla de red, la
        // orden ya no estará en progreso: devolvemos el reporte ya sincronizado en vez de un error.
        if (! $esVigilancia && $orden->sincronizado) {
            $reporteExistente = $orden->reportes()->latest()->first();

            if ($reporteExistente) {
                return response()->json([
                    'message' => 'Este reporte ya fue sincronizado previamente.',
                    'reporte' => new ReporteResource($reporteExistente),
                ], 200);
            }
        }

        if ($orden->estado !== EstadoOrden::EnProgreso) {
            return response()->json(['message' => 'La orden no está En Progreso.'], 422);
        }

        $this->validar($request, $orden);

        $data = $this->normalizarActividades($request->all());

        if ($esVigilancia) {
            // Un turno se identifica por su fecha; si la app reenvía el mismo turno (p. ej. la
            // respuesta anterior se perdió por falla de red), devolvemos el reporte ya guardado
            // en vez de duplicarlo.
            $reporteExistente = $orden->reportes()
                ->where('datos_servicio->fecha_turno', $data['fecha_turno'] ?? null)
                ->latest()
                ->first();

            if ($reporteExistente) {
                return response()->json([
                    'message' => 'Este turno ya fue sincronizado previamente.',
                    'reporte' => new ReporteResource($reporteExistente),
                ], 200);
            }
        } elseif ($orden->reportes()->exists()) {
            return response()->json(['message' => 'Esta orden ya tiene un reporte registrado.'], 422);
        }

        $reporte = match ($orden->tipo_servicio) {
            TipoServicio::Fraudes,
            TipoServicio::RevisionMedidores => $this->guardarFraudes($request, $orden, $data),
            TipoServicio::Vigilancia => $this->guardarVigilancia($request, $orden, $data),
            default => $this->guardarGenerico($request, $orden, $data),
        };

        return response()->json([
            'message' => 'Reporte registrado correctamente.',
            'reporte' => new ReporteResource($reporte),
        ], 201);
    }

    public function update(Request $request, OrdenJob $orden): JsonResponse
    {
        $user = $request->user();

        if (
            $user->personals_id !== $orden->personals_id &&
            ! $user->can('ordenes.ver')
        ) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        if ($orden->estado !== EstadoOrden::Rechazada) {
            return response()->json(['message' => 'Solo se pueden editar reportes de órdenes rechazadas.'], 422);
        }

        if ($orden->tipo_servicio === TipoServicio::Vigilancia) {
            return response()->json(['message' => 'Los reportes de Vigilancia no son editables individualmente.'], 422);
        }

        $reporte = $orden->reportes()->latest()->firstOrFail();

        $this->validar($request, $orden);

        $data = $this->normalizarActividades($request->all());

        $reporte = match ($orden->tipo_servicio) {
            TipoServicio::Fraudes,
            TipoServicio::RevisionMedidores => $this->actualizarFraudes($request, $orden, $reporte, $data),
            default => $this->actualizarGenerico($request, $orden, $reporte, $data),
        };

        return response()->json([
            'message' => 'Reporte actualizado correctamente.',
            'reporte' => new ReporteResource($reporte),
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    //  Validación por tipo de servicio
    // ─────────────────────────────────────────────────────────────

    private function validar(Request $request, OrdenJob $orden): void
    {
        $reglasTipo = match ($orden->tipo_servicio) {
            TipoServicio::Fraudes,
            TipoServicio::RevisionMedidores => $this->reglasFraudes($request),
            TipoServicio::Vigilancia => $this->reglasVigilancia(),
            TipoServicio::Investigacion => $this->reglasInvestigacion(),
            TipoServicio::GestionLegal => $this->reglasGestionLegal(),
            TipoServicio::AuditoriaCampo => $this->reglasAuditoriaCampo($orden),
            default => [],
        };

        $request->validate(array_merge([
            'latitud' => ['nullable', 'string'],
            'longitud' => ['nullable', 'string'],
            'observaciones' => ['nullable', 'string'],
            'imagenes' => ['nullable', 'array'],
            'imagenes.*' => ['string', $this->reglaPathDeLaEmpresa($request)],
            'video' => ['nullable', 'url', 'max:500'],
            'geo_referencia' => ['nullable', 'url', 'max:500'],
            'actividades' => ['nullable', 'array'],
            'actividades.*.descripcion_orden' => ['sometimes', 'required', 'string'],
            'actividades.*.actividades' => ['sometimes', 'required', 'string'],
        ], $reglasTipo));
    }

    /** @return array<string, mixed> */
    private function reglasFraudes(Request $request): array
    {
        return [
            'nombre_comercio' => ['required', 'string', 'max:255'],
            'tipo_comercio_id' => ['required', 'uuid', 'exists:tipos_comercio,id'],
            'numero_medidor' => ['nullable', 'string', 'max:255'],
            'coincide_medidor' => ['nullable', 'boolean'],
            'lectura' => ['nullable', 'string', 'max:255'],
            'marca_regulador_id' => ['nullable', 'uuid', 'exists:marcas_regulador,id'],
            'tipo_regulador_id' => ['nullable', 'uuid', 'exists:tipos_regulador,id'],
            'marca_medidor_id' => ['nullable', 'uuid', 'exists:marcas_medidor,id'],
            'tipo_notificacion_alerta_id' => ['nullable', 'uuid', 'exists:tipos_notificacion_alerta,id'],
            'anomalia' => ['nullable', 'array'],
            'anomalia.*' => ['uuid'],
            'tipo_imposibilidad_id' => ['nullable', 'uuid', 'exists:tipos_imposibilidad,id'],
            'hay_fuga_gas' => ['nullable', 'boolean'],
            'foto_detector_fuga' => [
                'nullable', 'string',
                $request->boolean('hay_fuga_gas') ? 'required' : 'nullable',
                $this->reglaPathDeLaEmpresa($request),
            ],
            'excede_capacidad' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, mixed> */
    private function reglasVigilancia(): array
    {
        return [
            'fecha_turno' => ['required', 'date'],
            'hora_inicio' => ['required', 'date_format:H:i,H:i:s'],
            'hora_fin' => ['required', 'date_format:H:i,H:i:s'],
            'hay_novedad' => ['nullable', 'boolean'],
            'descripcion_novedad' => ['nullable', 'string', 'required_if:hay_novedad,true'],
            'hay_reincidencia' => ['nullable', 'boolean'],
            'resultado_reporte_id' => ['nullable', 'uuid', 'exists:resultados_reporte,id'],
        ];
    }

    /** @return array<string, mixed> */
    private function reglasInvestigacion(): array
    {
        return [
            'convencion_id' => ['required', 'uuid', 'exists:convenciones,id'],
            'acompanamiento_tecnico' => ['nullable', 'boolean'],
            'acompanante_nombre' => ['nullable', 'string', 'max:255', 'required_if:acompanamiento_tecnico,true'],
            'acompanante_identificacion' => ['nullable', 'string', 'max:50', 'required_if:acompanamiento_tecnico,true'],
            'acompanante_cargo' => ['nullable', 'string', 'max:255', 'required_if:acompanamiento_tecnico,true'],
            'acompanante_cuadrilla' => ['nullable', 'string', 'max:100'],
            'tipo_investigado' => ['required', 'in:persona,comercio'],
            'nombre_investigado' => ['nullable', 'string', 'max:255'],
            'identificacion' => ['nullable', 'string', 'max:50'],
            'descripcion_fisica' => ['nullable', 'string'],
            'nombre_comercio_inv' => ['nullable', 'string', 'max:255'],
            'tipo_comercio_inv_id' => ['nullable', 'uuid', 'exists:tipos_comercio,id'],
            'resultado_reporte_id' => ['nullable', 'uuid', 'exists:resultados_reporte,id'],
        ];
    }

    /** @return array<string, mixed> */
    private function reglasGestionLegal(): array
    {
        return [
            'fecha_ejecucion' => ['required', 'date'],
            'departamento' => ['nullable', 'string', 'max:255'],
            'municipio' => ['nullable', 'string', 'max:255'],
            'radio_objetivo' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, mixed> */
    private function reglasAuditoriaCampo(OrdenJob $orden): array
    {
        $comunes = [
            'nombre_atiende' => ['required', 'string', 'max:255'],
            'vinculo_usuario' => ['nullable', 'in:titular,familiar,vecino,otro'],
            'celular_atiende' => ['nullable', 'string', 'max:20'],
            'fecha_hora_atencion' => ['required', 'date'],
            'autoriza' => ['required', 'boolean'],
            'cedula_usuario' => ['required', 'string', 'max:20'],
            'cedula_auditor' => ['required', 'string', 'max:20'],
            'firma_usuario' => ['nullable', 'string'],
            'firma_auditor' => ['nullable', 'string'],
            'urls_evidencia' => ['nullable', 'array'],
            'urls_evidencia.*' => ['url'],
        ];

        if ($orden->tipo_auditoria_campo === 'visita_calidad_medidores_digitales') {
            return array_merge($comunes, [
                'tecnico_atendio_medidor' => ['required', 'string', 'max:255'],
                'numero_medidor_instalado' => ['required', 'string', 'max:100'],
                'resultado_medidor' => ['required', 'in:conforme,no_conforme,seguimiento_urgente'],
                'informo_gratuidad_medidor' => ['nullable', 'boolean'],
                'solicito_dinero_medidor' => ['nullable', 'boolean'],
                'gestion_conforme_medidor' => ['nullable', 'boolean'],
                'requiere_seguimiento_medidor' => ['nullable', 'boolean'],
                'hallazgos_medidor' => ['nullable', 'string'],
            ]);
        }

        return array_merge($comunes, [
            'recibio_visita' => ['required', 'boolean'],
            'se_realizo_revision' => ['required', 'boolean'],
            'fecha_ejecucion' => ['nullable', 'date'],
            'tiempo_ejecucion' => ['nullable', 'string', 'max:100'],
            'descripcion_trabajo' => ['nullable', 'string'],
            'se_detecto_fuga' => ['nullable', 'boolean'],
            'ppm' => ['nullable', 'string', 'max:50'],
            'vol_porcentaje' => ['nullable', 'string', 'max:50'],
            'problema_persiste' => ['nullable', 'boolean'],
            'evidencia_reparacion' => ['nullable', 'boolean'],
            'servicio_correcto' => ['nullable', 'boolean'],
            'se_usaron_materiales' => ['nullable', 'boolean'],
            'quien_suministro_materiales' => ['nullable', 'in:tecnico,usuario,empresa'],
            'usuario_asumio_costo' => ['nullable', 'boolean'],
            'valor_pagado' => ['nullable', 'string', 'max:100'],
            'medio_pago' => ['nullable', 'in:efectivo,transferencia'],
            'entrego_factura' => ['nullable', 'boolean'],
            'tecnico_dejo_documento' => ['nullable', 'string', 'max:255'],
            'informaron_no_estaba' => ['nullable', 'boolean'],
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    //  Métodos de guardado (store)
    // ─────────────────────────────────────────────────────────────

    /** @param array<string, mixed> $data */
    private function guardarFraudes(Request $request, OrdenJob $orden, array $data): reportes
    {
        $comercioRecord = comercio::create([
            'nombre_comercio' => $data['nombre_comercio'],
            'tipo_comercio_id' => $data['tipo_comercio_id'],
        ]);

        $imagenes = $this->normalizarImagenes($data);
        $fotoDetectorFuga = $data['foto_detector_fuga'] ?? null;
        $fotoDetectorData = $this->cargarFotoDetectorData($fotoDetectorFuga);
        $fotoDetectorMimeType = $this->cargarFotoDetectorMimeType($fotoDetectorFuga);

        $ubicacionRecord = ubicacion::create([
            'latitud' => $data['latitud'] ?? null,
            'longitud' => $data['longitud'] ?? null,
            'direccion' => $orden->direccion,
        ]);

        $reporte = reportes::create([
            'orden_job_id' => $orden->id,
            'personals_id' => $request->user()->personals_id,
            'numero_orden' => $orden->numero_orden,
            'ubicacions_id' => $ubicacionRecord->id,
            'comercios_id' => $comercioRecord->id,
            'numero_medidor' => $data['numero_medidor'] ?? null,
            'coincide_medidor' => $data['coincide_medidor'] ?? null,
            'lectura' => $data['lectura'] ?? null,
            'marca_regulador_id' => $data['marca_regulador_id'] ?? null,
            'tipo_regulador_id' => $data['tipo_regulador_id'] ?? null,
            'marca_medidor_id' => $data['marca_medidor_id'] ?? null,
            'tipo_notificacion_alerta_id' => $data['tipo_notificacion_alerta_id'] ?? null,
            'anomalia' => $data['anomalia'] ?? null,
            'tipo_imposibilidad_id' => $data['tipo_imposibilidad_id'] ?? null,
            'hay_fuga_gas' => $data['hay_fuga_gas'] ?? false,
            'excede_capacidad' => $data['excede_capacidad'] ?? false,
            'imagenes' => $imagenes,
            'video' => $data['video'] ?? null,
            'geo_referencia' => $data['geo_referencia'] ?? null,
            'observaciones' => $data['observaciones'] ?? null,
            'actividades' => $data['actividades'] ?? null,
        ]);

        $orden->update(['estado' => EstadoOrden::EnRevision, 'sincronizado' => true]);
        $this->notificarCoordinador($orden);
        $this->notificarIncidenciasIfApply($orden, $data, $fotoDetectorFuga, $fotoDetectorData, $fotoDetectorMimeType);

        return $reporte;
    }

    /** @param array<string, mixed> $data */
    private function guardarGenerico(Request $request, OrdenJob $orden, array $data): reportes
    {
        $datosServicio = $this->extraerDatosServicio($data);

        $ubicacionRecord = ubicacion::create([
            'latitud' => $data['latitud'] ?? null,
            'longitud' => $data['longitud'] ?? null,
            'direccion' => $orden->direccion ?? null,
        ]);

        $reporte = reportes::create([
            'orden_job_id' => $orden->id,
            'personals_id' => $request->user()->personals_id,
            'numero_orden' => $orden->numero_orden,
            'ubicacions_id' => $ubicacionRecord->id,
            'observaciones' => $data['observaciones'] ?? null,
            'imagenes' => $data['imagenes'] ?? null,
            'video' => $data['video'] ?? null,
            'geo_referencia' => $data['geo_referencia'] ?? null,
            'datos_servicio' => $datosServicio ?: null,
            'actividades' => $data['actividades'] ?? null,
        ]);

        $orden->update(['estado' => EstadoOrden::EnRevision, 'sincronizado' => true]);
        $this->notificarCoordinador($orden);

        return $reporte;
    }

    /** @param array<string, mixed> $data */
    private function guardarVigilancia(Request $request, OrdenJob $orden, array $data): reportes
    {
        $datosServicio = $this->extraerDatosServicio($data);

        $ubicacionRecord = ubicacion::create([
            'latitud' => $data['latitud'] ?? null,
            'longitud' => $data['longitud'] ?? null,
            'direccion' => $orden->direccion ?? null,
        ]);

        // La orden permanece en EnProgreso; no se notifica al coordinador
        return reportes::create([
            'orden_job_id' => $orden->id,
            'personals_id' => $request->user()->personals_id,
            'numero_orden' => $orden->numero_orden,
            'ubicacions_id' => $ubicacionRecord->id,
            'observaciones' => $data['observaciones'] ?? null,
            'imagenes' => $data['imagenes'] ?? null,
            'video' => $data['video'] ?? null,
            'geo_referencia' => $data['geo_referencia'] ?? null,
            'datos_servicio' => $datosServicio ?: null,
            'actividades' => $data['actividades'] ?? null,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    //  Métodos de actualización (update)
    // ─────────────────────────────────────────────────────────────

    /** @param array<string, mixed> $data */
    private function actualizarFraudes(Request $request, OrdenJob $orden, reportes $reporte, array $data): reportes
    {
        if ($reporte->comercio) {
            $reporte->comercio->update([
                'nombre_comercio' => $data['nombre_comercio'],
                'tipo_comercio_id' => $data['tipo_comercio_id'],
            ]);
        } else {
            $comercioRecord = comercio::create([
                'nombre_comercio' => $data['nombre_comercio'],
                'tipo_comercio_id' => $data['tipo_comercio_id'],
            ]);
            $reporte->comercios_id = $comercioRecord->id;
        }

        $imagenesNuevas = $this->normalizarImagenes($data) ?? [];
        $imagenesFinales = array_values(array_merge($reporte->imagenes ?? [], $imagenesNuevas));

        $fotoDetectorFuga = $data['foto_detector_fuga'] ?? null;
        $fotoDetectorData = $this->cargarFotoDetectorData($fotoDetectorFuga);
        $fotoDetectorMimeType = $this->cargarFotoDetectorMimeType($fotoDetectorFuga);

        $ubicacionRecord = ubicacion::create([
            'latitud' => $data['latitud'] ?? null,
            'longitud' => $data['longitud'] ?? null,
            'direccion' => $orden->direccion,
        ]);

        $reporte->update([
            'ubicacions_id' => $ubicacionRecord->id,
            'numero_medidor' => $data['numero_medidor'] ?? null,
            'coincide_medidor' => $data['coincide_medidor'] ?? null,
            'lectura' => $data['lectura'] ?? null,
            'marca_regulador_id' => $data['marca_regulador_id'] ?? null,
            'tipo_regulador_id' => $data['tipo_regulador_id'] ?? null,
            'marca_medidor_id' => $data['marca_medidor_id'] ?? null,
            'tipo_notificacion_alerta_id' => $data['tipo_notificacion_alerta_id'] ?? null,
            'anomalia' => $data['anomalia'] ?? null,
            'tipo_imposibilidad_id' => $data['tipo_imposibilidad_id'] ?? null,
            'hay_fuga_gas' => $data['hay_fuga_gas'] ?? false,
            'excede_capacidad' => $data['excede_capacidad'] ?? false,
            'imagenes' => $imagenesFinales ?: null,
            'video' => $data['video'] ?? null,
            'geo_referencia' => $data['geo_referencia'] ?? null,
            'observaciones' => $data['observaciones'] ?? null,
            'actividades' => $data['actividades'] ?? null,
        ]);

        $orden->update(['estado' => EstadoOrden::EnRevision, 'sincronizado' => true]);
        $this->notificarCoordinador($orden);
        $this->notificarIncidenciasIfApply($orden, $data, $fotoDetectorFuga, $fotoDetectorData, $fotoDetectorMimeType);

        return $reporte->fresh();
    }

    /** @param array<string, mixed> $data */
    private function actualizarGenerico(Request $request, OrdenJob $orden, reportes $reporte, array $data): reportes
    {
        $datosServicio = $this->extraerDatosServicio($data);

        $imagenesNuevas = $data['imagenes'] ?? [];
        $imagenesFinales = array_values(array_merge($reporte->imagenes ?? [], $imagenesNuevas));

        $ubicacionRecord = ubicacion::create([
            'latitud' => $data['latitud'] ?? null,
            'longitud' => $data['longitud'] ?? null,
            'direccion' => $orden->direccion ?? null,
        ]);

        $reporte->update([
            'ubicacions_id' => $ubicacionRecord->id,
            'observaciones' => $data['observaciones'] ?? null,
            'imagenes' => $imagenesFinales ?: null,
            'video' => $data['video'] ?? null,
            'geo_referencia' => $data['geo_referencia'] ?? null,
            'datos_servicio' => $datosServicio ?: null,
            'actividades' => $data['actividades'] ?? null,
        ]);

        $orden->update(['estado' => EstadoOrden::EnRevision, 'sincronizado' => true]);
        $this->notificarCoordinador($orden);

        return $reporte->fresh();
    }

    // ─────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────

    /**
     * Regla de validación: exige que un path de S3 pertenezca al company_id del usuario
     * autenticado (todos los paths legítimos vienen de POST /api/upload, que ya antepone
     * ese prefijo).
     */
    private function reglaPathDeLaEmpresa(Request $request): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
            $companyId = $request->user()->company_id;

            if (! str_starts_with((string) $value, $companyId.'/')) {
                $fail('El archivo debe pertenecer a tu empresa.');
            }
        };
    }

    /**
     * Elimina del array los campos que se guardan en columnas propias del reporte,
     * dejando solo lo que va en el JSON datos_servicio.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function extraerDatosServicio(array $data): array
    {
        $excluidos = ['observaciones', 'imagenes', 'video', 'geo_referencia', 'actividades', 'latitud', 'longitud'];

        return array_diff_key($data, array_flip($excluidos));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizarActividades(array $data): array
    {
        if (! empty($data['actividades']) && is_array($data['actividades'])) {
            foreach ($data['actividades'] as $i => $item) {
                if (empty($item['uuid'])) {
                    $data['actividades'][$i]['uuid'] = (string) Str::uuid();
                }
            }
        }

        return $data;
    }

    /**
     * Fusiona imagenes[] y foto_detector_fuga en un único array de rutas S3.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, string>|null
     */
    private function normalizarImagenes(array $data): ?array
    {
        $imagenes = array_values($data['imagenes'] ?? []);

        $foto = $data['foto_detector_fuga'] ?? null;
        if ($foto) {
            $imagenes[] = is_array($foto) ? reset($foto) : $foto;
        }

        return $imagenes !== [] ? $imagenes : null;
    }

    private function cargarFotoDetectorData(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        try {
            return Storage::disk('s3')->exists($path)
                ? Storage::disk('s3')->get($path)
                : null;
        } catch (UnableToCheckExistence) {
            return null;
        }
    }

    private function cargarFotoDetectorMimeType(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        try {
            return Storage::disk('s3')->exists($path)
                ? Storage::disk('s3')->mimeType($path)
                : null;
        } catch (UnableToCheckExistence) {
            return null;
        }
    }

    private function notificarCoordinador(OrdenJob $orden): void
    {
        $email = $orden->creadoPor?->email;
        $tecnico = $orden->personal;

        if (! $email || ! $tecnico) {
            return;
        }

        try {
            Mail::to($email)->send(new ReporteEnRevision($orden, $tecnico));
        } catch (\Exception $e) {
            Log::error('No se pudo notificar al coordinador sobre el reporte.', [
                'orden' => $orden->numero_orden,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function notificarIncidenciasIfApply(
        OrdenJob $orden,
        array $data,
        ?string $fotoDetectorFuga,
        ?string $fotoDetectorData,
        ?string $fotoDetectorMimeType,
    ): void {
        $bypassId = TipoAnomalia::where('activo', true)->where('nombre', 'Bypass')->value('id');
        $hasBypass = $bypassId &&
            ! empty($data['anomalia']) &&
            in_array($bypassId, $data['anomalia'], true);

        if (! ($data['hay_fuga_gas'] ?? false) && ! ($data['excede_capacidad'] ?? false) && ! $hasBypass) {
            return;
        }

        $correos = CorreoAlerta::where('activo', true)->pluck('email');

        if ($correos->isEmpty()) {
            return;
        }

        $incidencias = [];

        if ($data['hay_fuga_gas'] ?? false) {
            $incidencias[] = 'Hay Fuga de Gas';
        }

        if ($data['excede_capacidad'] ?? false) {
            $incidencias[] = 'Excede Capacidad';
        }

        if ($hasBypass) {
            $incidencias[] = 'Bypass';
        }

        foreach ($correos as $email) {
            try {
                Mail::to($email)->send(new IncidenciaDetectada(
                    $orden,
                    $incidencias,
                    $fotoDetectorFuga,
                    $fotoDetectorData,
                    $fotoDetectorMimeType,
                    $fotoDetectorFuga ? basename($fotoDetectorFuga) : null,
                ));
            } catch (\Exception $e) {
                Log::error('No se pudo enviar alerta de incidencia.', [
                    'orden' => $orden->numero_orden,
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
