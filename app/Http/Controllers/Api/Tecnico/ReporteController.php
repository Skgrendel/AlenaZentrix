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
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
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

        return new ReporteResource($reporte->load(['ubicacion', 'comercio.tipoComercio']));
    }

    public function store(Request $request, OrdenJob $orden): JsonResponse
    {
        if (! $this->puedeEscribirReporte($request->user(), $orden)) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        // La orden se bloquea para que un doble envío simultáneo no cree dos reportes.
        return DB::transaction(function () use ($request, $orden): JsonResponse {
            $orden = OrdenJob::lockForUpdate()->findOrFail($orden->id);
            $esVigilancia = $orden->tipo_servicio === TipoServicio::Vigilancia;

            if ($esVigilancia) {
                // Un turno se identifica por su fecha; si la app reenvía el mismo turno (p. ej. la
                // respuesta anterior se perdió por falla de red, o quedó en la cola offline tras el
                // cierre), devolvemos el reporte ya guardado en vez de duplicarlo.
                $fechaTurno = $request->input('fecha_turno');
                $reporteExistente = is_string($fechaTurno) && $fechaTurno !== ''
                    ? $orden->reportes()->where('datos_servicio->fecha_turno', $fechaTurno)->latest()->first()
                    : null;

                if ($reporteExistente) {
                    return response()->json([
                        'message' => 'Este turno ya fue sincronizado previamente.',
                        'reporte' => new ReporteResource($reporteExistente),
                    ], 200);
                }

                if (! in_array($orden->estado, [EstadoOrden::EnProgreso, EstadoOrden::EnRevision], true)) {
                    return response()->json(['message' => 'La orden no está En Progreso.'], 422);
                }
            } else {
                // Servicios de un solo reporte salen de "en progreso" en cuanto el reporte se guarda.
                // Si la app reenvía el mismo reporte porque perdió la respuesta 201, devolvemos el
                // reporte ya sincronizado en vez de un error.
                if ($orden->sincronizado) {
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
            }

            $this->validar($request, $orden, null, true);

            $data = $this->normalizarActividades($request->all());

            if ($esVigilancia) {
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
        });
    }

    public function update(Request $request, OrdenJob $orden): JsonResponse
    {
        if (! $this->puedeEscribirReporte($request->user(), $orden)) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        if ($orden->tipo_servicio === TipoServicio::Vigilancia) {
            return response()->json(['message' => 'Los reportes de Vigilancia no son editables individualmente.'], 422);
        }

        return DB::transaction(function () use ($request, $orden): JsonResponse {
            $orden = OrdenJob::lockForUpdate()->findOrFail($orden->id);
            $esFraudes = in_array($orden->tipo_servicio, [TipoServicio::Fraudes, TipoServicio::RevisionMedidores], true);

            if ($orden->estado !== EstadoOrden::Rechazada) {
                // Reenvío tras perder la respuesta: si el reporte ya contiene lo enviado, es idempotente.
                $reporte = $orden->reportes()->latest()->first();

                if ($reporte && ! $reporte->requiere_correccion) {
                    $this->validar($request, $orden, $reporte);
                    $data = $this->normalizarActividades($request->all());

                    if ($this->contenidoCoincide($reporte, $data, $esFraudes)) {
                        return response()->json([
                            'message' => 'Este reporte ya fue actualizado previamente.',
                            'reporte' => new ReporteResource($reporte),
                        ]);
                    }
                }

                return response()->json(['message' => 'Solo se pueden editar reportes de órdenes rechazadas.'], 422);
            }

            $reporte = $orden->reportes()->latest()->firstOrFail();

            $this->validar($request, $orden, $reporte);

            $data = $this->normalizarActividades($request->all());

            $reporte = $esFraudes
                ? $this->actualizarFraudes($request, $orden, $reporte, $data)
                : $this->actualizarGenerico($request, $orden, $reporte, $data);

            return response()->json([
                'message' => 'Reporte actualizado correctamente.',
                'reporte' => new ReporteResource($reporte),
            ]);
        });
    }

    /** Devuelve un turno concreto de una orden de Vigilancia (p. ej. para corregirlo). */
    public function showTurnoVigilancia(Request $request, OrdenJob $orden, reportes $reporte): JsonResponse|ReporteResource
    {
        $user = $request->user();

        if ($orden->tipo_servicio !== TipoServicio::Vigilancia || $reporte->orden_job_id !== $orden->id) {
            return response()->json(['message' => 'Turno no encontrado.'], 404);
        }

        if (! $user->personals_id && ! $user->can('ordenes.ver')) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        return new ReporteResource($reporte->load('ubicacion'));
    }

    /**
     * Corrige un turno de Vigilancia que el coordinador marcó para corrección. A diferencia del
     * resto de servicios, la orden permanece activa: no pasa por Rechazada ni cambia de estado,
     * y los demás turnos no se ven afectados.
     */
    public function updateTurnoVigilancia(Request $request, OrdenJob $orden, reportes $reporte): JsonResponse
    {
        $user = $request->user();

        if ($orden->tipo_servicio !== TipoServicio::Vigilancia) {
            return response()->json(['message' => 'Solo los turnos de Vigilancia se corrigen individualmente.'], 422);
        }

        if (! $this->puedeEscribirReporte($user, $orden)) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        if ($reporte->orden_job_id !== $orden->id) {
            return response()->json(['message' => 'El turno no pertenece a esta orden.'], 404);
        }

        return DB::transaction(function () use ($request, $orden, $reporte, $user): JsonResponse {
            $orden = OrdenJob::lockForUpdate()->findOrFail($orden->id);
            $reporte = reportes::lockForUpdate()->findOrFail($reporte->id);

            if (! $reporte->requiere_correccion) {
                // Reenvío tras perder la respuesta: mismo técnico y mismo contenido => idempotente.
                if (filled($user->personals_id) && $reporte->personals_id === $user->personals_id) {
                    $this->validar($request, $orden, $reporte);
                    $data = $this->normalizarActividades($request->all());

                    if ($this->contenidoCoincide($reporte, $data, false)) {
                        return response()->json([
                            'message' => 'Este turno ya fue corregido previamente.',
                            'reporte' => new ReporteResource($reporte),
                        ]);
                    }
                }

                return response()->json(['message' => 'Este turno no fue marcado para corrección.'], 422);
            }

            $this->validar($request, $orden, $reporte);

            $data = $this->normalizarActividades($request->all());

            $turnoDuplicado = $orden->reportes()
                ->where('id', '!=', $reporte->id)
                ->where('datos_servicio->fecha_turno', $data['fecha_turno'] ?? null)
                ->exists();

            if ($turnoDuplicado) {
                return response()->json(['message' => 'Ya existe otro turno registrado con esa fecha.'], 422);
            }

            $imagenesFinales = array_values(array_unique(array_merge($reporte->imagenes ?? [], $data['imagenes'] ?? [])));

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
                'geo_referencia' => $data['geo_referencia'] ?? $reporte->geo_referencia,
                'datos_servicio' => $this->extraerDatosServicio($data) ?: null,
                'actividades' => $data['actividades'] ?? null,
                'requiere_correccion' => false,
            ]);

            $this->notificarCoordinador($orden);

            return response()->json([
                'message' => 'Turno corregido correctamente.',
                'reporte' => new ReporteResource($reporte->fresh()),
            ]);
        });
    }

    // ─────────────────────────────────────────────────────────────
    //  Validación por tipo de servicio
    // ─────────────────────────────────────────────────────────────

    /**
     * Solo el técnico dueño de la orden (o quien tenga permiso real de edición) escribe reportes.
     * En Vigilancia cualquier usuario con técnico asociado puede reportar turnos.
     */
    private function puedeEscribirReporte(User $user, OrdenJob $orden): bool
    {
        if ($orden->tipo_servicio === TipoServicio::Vigilancia) {
            return filled($user->personals_id);
        }

        if (filled($user->personals_id) && $user->personals_id === $orden->personals_id) {
            return true;
        }

        return $user->can('ordenes.editar');
    }

    private function validar(Request $request, OrdenJob $orden, ?reportes $reporte = null, bool $esCreacion = false): void
    {
        $guardadas = $this->rutasGuardadas($reporte);

        $reglasTipo = match ($orden->tipo_servicio) {
            TipoServicio::Fraudes,
            TipoServicio::RevisionMedidores => $this->reglasFraudes($request, $reporte, $esCreacion),
            TipoServicio::Vigilancia => $this->reglasVigilancia($request),
            TipoServicio::Investigacion => $this->reglasInvestigacion($request),
            TipoServicio::GestionLegal => $this->reglasGestionLegal(),
            TipoServicio::AuditoriaCampo => $this->reglasAuditoriaCampo($request, $orden, $reporte),
            default => [],
        };

        $request->validate(array_merge([
            'latitud' => ['nullable', 'string'],
            'longitud' => ['nullable', 'string'],
            'observaciones' => ['nullable', 'string'],
            'imagenes' => ['nullable', 'array', 'max:30'],
            'imagenes.*' => ['string', $this->reglaPathDeLaEmpresa($request, $guardadas)],
            'video' => ['nullable', 'url', 'max:500'],
            'geo_referencia' => ['nullable', 'url', 'max:500'],
            'actividades' => ['nullable', 'array'],
            'actividades.*.descripcion_orden' => ['sometimes', 'required', 'string'],
            'actividades.*.actividades' => ['sometimes', 'required', 'string'],
        ], $reglasTipo));
    }

    /**
     * Rutas de archivos ya guardadas en el reporte que se edita (imagenes y fotos_*). Se aceptan
     * tal cual al reenviarlas, incluidas las legacy sin prefijo de empresa creadas desde la web.
     *
     * @return array<int, string>
     */
    private function rutasGuardadas(?reportes $reporte): array
    {
        if (! $reporte) {
            return [];
        }

        $rutas = array_filter((array) ($reporte->imagenes ?? []), 'is_string');

        foreach ((array) ($reporte->datos_servicio ?? []) as $clave => $valor) {
            if (! is_string($clave) || ! str_starts_with($clave, 'foto')) {
                continue;
            }

            foreach ((array) $valor as $ruta) {
                if (is_string($ruta)) {
                    $rutas[] = $ruta;
                }
            }
        }

        return array_values(array_unique($rutas));
    }

    /**
     * Regla para ids de catálogo: deben existir, no estar eliminados y ser de la empresa del usuario.
     */
    private function catalogoDeLaEmpresa(Request $request, string $tabla): Exists
    {
        return Rule::exists($tabla, 'id')->where(
            fn ($query) => $query->where('company_id', $request->user()->company_id)->whereNull('deleted_at')
        );
    }

    /**
     * Regla para ids de tipos de anomalía; acepta además los ya guardados en el reporte que se corrige
     * (aunque luego se hayan desactivado).
     *
     * @param  array<int, string>  $permitidos
     */
    private function reglaAnomalia(Request $request, array $permitidos, bool $soloActivas): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($request, $permitidos, $soloActivas): void {
            if (! is_string($value)) {
                $fail('El valor de :attribute no es válido.');

                return;
            }

            if (in_array($value, $permitidos, true)) {
                return;
            }

            $query = TipoAnomalia::query()
                ->withoutGlobalScopes()
                ->where('id', $value)
                ->whereNull('deleted_at')
                ->where('company_id', $request->user()->company_id);

            if ($soloActivas) {
                $query->where('activo', true);
            }

            if (! $query->exists()) {
                $fail('El valor de :attribute no es válido.');
            }
        };
    }

    /** @return array<string, mixed> */
    private function reglasFraudes(Request $request, ?reportes $reporte, bool $esCreacion): array
    {
        $guardadas = $this->rutasGuardadas($reporte);

        return [
            'nombre_comercio' => ['required', 'string', 'max:255'],
            'tipo_comercio_id' => ['required', 'uuid', $this->catalogoDeLaEmpresa($request, 'tipos_comercio')],
            'numero_medidor' => ['nullable', 'string', 'max:255'],
            'coincide_medidor' => ['nullable', 'boolean'],
            'lectura' => ['nullable', 'string', 'max:255'],
            'marca_regulador_id' => ['nullable', 'uuid', $this->catalogoDeLaEmpresa($request, 'marcas_regulador')],
            'tipo_regulador_id' => ['nullable', 'uuid', $this->catalogoDeLaEmpresa($request, 'tipos_regulador')],
            'marca_medidor_id' => ['nullable', 'uuid', $this->catalogoDeLaEmpresa($request, 'marcas_medidor')],
            'tipo_notificacion_alerta_id' => ['nullable', 'uuid', $this->catalogoDeLaEmpresa($request, 'tipos_notificacion_alerta')],
            'anomalia' => ['nullable', 'array'],
            'anomalia.*' => ['uuid', $this->reglaAnomalia($request, (array) ($reporte?->anomalia ?? []), false)],
            'tipo_imposibilidad_id' => ['nullable', 'uuid', $this->catalogoDeLaEmpresa($request, 'tipos_imposibilidad')],
            'hay_fuga_gas' => ['nullable', 'boolean'],
            // Obligatoria solo al crear; al corregir es opcional (la ya guardada vive en imagenes).
            'foto_detector_fuga' => [
                'nullable', 'string',
                $esCreacion && $request->boolean('hay_fuga_gas') ? 'required' : 'nullable',
                $this->reglaPathDeLaEmpresa($request, $guardadas),
            ],
            'excede_capacidad' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, mixed> */
    private function reglasVigilancia(Request $request): array
    {
        return [
            'fecha_turno' => ['required', 'date'],
            'hora_inicio' => ['required', 'date_format:H:i,H:i:s'],
            'hora_fin' => ['required', 'date_format:H:i,H:i:s'],
            'hay_novedad' => ['nullable', 'boolean'],
            'descripcion_novedad' => ['nullable', 'string', 'required_if:hay_novedad,true'],
            'hay_reincidencia' => ['nullable', 'boolean'],
            'resultado_reporte_id' => ['nullable', 'uuid', $this->catalogoDeLaEmpresa($request, 'resultados_reporte')],
        ];
    }

    /** @return array<string, mixed> */
    private function reglasInvestigacion(Request $request): array
    {
        return [
            'convencion_id' => ['required', 'uuid', $this->catalogoDeLaEmpresa($request, 'convenciones')],
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
            'tipo_comercio_inv_id' => ['nullable', 'uuid', $this->catalogoDeLaEmpresa($request, 'tipos_comercio')],
            'resultado_reporte_id' => ['nullable', 'uuid', $this->catalogoDeLaEmpresa($request, 'resultados_reporte')],
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
    private function reglasAuditoriaCampo(Request $request, OrdenJob $orden, ?reportes $reporte): array
    {
        if ($orden->tipo_auditoria_campo === 'visita_calidad_suspensiones') {
            return $this->reglasSuspensiones($request, $reporte);
        }

        $firma = ['nullable', 'string', 'max:700000', 'regex:/^data:image\/(png|jpeg);base64,[A-Za-z0-9+\/=\r\n]+$/'];

        $comunes = [
            'nombre_atiende' => ['required', 'string', 'max:255'],
            'vinculo_usuario' => ['nullable', 'in:titular,familiar,vecino,otro'],
            'celular_atiende' => ['nullable', 'string', 'max:20'],
            'fecha_hora_atencion' => ['required', 'date'],
            'autoriza' => ['required', 'boolean'],
            'cedula_usuario' => ['required', 'string', 'max:20'],
            'cedula_auditor' => ['required', 'string', 'max:20'],
            'firma_usuario' => $firma,
            'firma_auditor' => $firma,
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

    /** @return array<string, mixed> */
    private function reglasSuspensiones(Request $request, ?reportes $reporte): array
    {
        $fotoPath = $this->reglaPathDeLaEmpresa($request, $this->rutasGuardadas($reporte));
        $anomaliasGuardadas = (array) (($reporte?->datos_servicio ?? [])['anomalias_medidor'] ?? []);

        $esSonda = $request->boolean('suspension_ejecutada')
            && in_array($request->input('metodo_suspension'), ['sonda_ips', 'sonda_cts'], true);

        return [
            'categoria_campo' => ['required', 'in:comercial,residencial,industrial'],
            'suspension_ejecutada' => ['required', 'boolean'],
            'metodo_suspension' => ['required_if:suspension_ejecutada,true,1', 'nullable', 'in:sonda_ips,sonda_cts,taponamiento_acometida,taponamiento_multifamiliar'],
            'serie_sonda' => [$esSonda ? 'required' : 'nullable', 'string', 'regex:/^\d+$/', 'max:50'],
            'serie_caps' => [$esSonda ? 'required' : 'nullable', 'string', 'regex:/^\d+$/', 'max:50'],
            'motivo_no_ejecucion' => ['required_if:suspension_ejecutada,false,0', 'nullable', 'in:no_ubico_direccion,predio_cerrado,usuario_impide,riesgo_sitio,redes_terraza,usuario_taponado'],
            'fotos_fachada' => ['required', 'array', 'min:3', 'max:20'],
            'fotos_fachada.*' => ['string', $fotoPath],
            'lectura_medidor' => ['required_if:suspension_ejecutada,true,1', 'nullable', 'numeric'],
            'anomalias_medidor' => ['nullable', 'array'],
            'anomalias_medidor.*' => ['string', $this->reglaAnomalia($request, $anomaliasGuardadas, true)],
            'condicion_sellos' => ['nullable', 'string', 'max:2000'],
            'elementos_rotos' => ['nullable', 'string', 'max:2000'],
            'fotos_medidor' => ['required_if:suspension_ejecutada,true,1', 'nullable', 'array', 'min:1', 'max:10'],
            'fotos_medidor.*' => ['string', $fotoPath],
            'fotos_suspension' => ['required_if:suspension_ejecutada,true,1', 'nullable', 'array', 'min:3', 'max:10'],
            'fotos_suspension.*' => ['string', $fotoPath],
            'justificacion_no_ejecucion' => ['required_if:suspension_ejecutada,false,0', 'nullable', 'string'],
            'fotos_no_ejecucion' => ['required_if:suspension_ejecutada,false,0', 'nullable', 'array', 'min:1', 'max:10'],
            'fotos_no_ejecucion.*' => ['string', $fotoPath],
        ];
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
            'personals_id' => $request->user()->personals_id ?? $orden->personals_id,
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
            'personals_id' => $request->user()->personals_id ?? $orden->personals_id,
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
            'personals_id' => $request->user()->personals_id ?? $orden->personals_id,
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
        $imagenesFinales = array_values(array_unique(array_merge($reporte->imagenes ?? [], $imagenesNuevas)));

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
            'geo_referencia' => $data['geo_referencia'] ?? $reporte->geo_referencia,
            'observaciones' => $data['observaciones'] ?? null,
            'actividades' => $data['actividades'] ?? null,
            'requiere_correccion' => false,
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
        $imagenesFinales = array_values(array_unique(array_merge($reporte->imagenes ?? [], $imagenesNuevas)));

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
            'geo_referencia' => $data['geo_referencia'] ?? $reporte->geo_referencia,
            'datos_servicio' => $datosServicio ?: null,
            'actividades' => $data['actividades'] ?? null,
            'requiere_correccion' => false,
        ]);

        $orden->update(['estado' => EstadoOrden::EnRevision, 'sincronizado' => true]);
        $this->notificarCoordinador($orden);

        return $reporte->fresh();
    }

    // ─────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────

    /**
     * Regla de validación: un path de S3 debe pertenecer al company_id del usuario autenticado
     * (todos los paths legítimos vienen de POST /api/upload, que ya antepone ese prefijo) y ser
     * una ruta limpia: sin segmentos '.' o '..', sin '//', sin '\' ni caracteres de control.
     * Se aceptan sin más las rutas ya guardadas en el reporte que se edita (legacy sin prefijo).
     *
     * @param  array<int, string>  $permitidas
     */
    private function reglaPathDeLaEmpresa(Request $request, array $permitidas = []): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($request, $permitidas): void {
            if (is_string($value) && in_array($value, $permitidas, true)) {
                return;
            }

            $companyId = (string) $request->user()->company_id;

            if (
                ! is_string($value)
                || $companyId === ''
                || ! preg_match('/^[A-Za-z0-9._\-\/]+$/D', $value)
            ) {
                $fail('El archivo debe pertenecer a tu empresa.');

                return;
            }

            $segmentos = explode('/', $value);

            if (count($segmentos) < 2 || $segmentos[0] !== $companyId) {
                $fail('El archivo debe pertenecer a tu empresa.');

                return;
            }

            foreach ($segmentos as $segmento) {
                if ($segmento === '' || $segmento === '.' || $segmento === '..') {
                    $fail('El archivo debe pertenecer a tu empresa.');

                    return;
                }
            }
        };
    }

    /**
     * Indica si el reporte ya contiene los mismos datos enviados (reenvío idempotente).
     *
     * @param  array<string, mixed>  $data
     */
    private function contenidoCoincide(reportes $reporte, array $data, bool $esFraudes): bool
    {
        if ($this->canonico($data['observaciones'] ?? null) !== $this->canonico($reporte->observaciones)) {
            return false;
        }

        $guardadas = (array) ($reporte->imagenes ?? []);
        $enviadas = $esFraudes ? ($this->normalizarImagenes($data) ?? []) : (array) ($data['imagenes'] ?? []);

        foreach ($enviadas as $ruta) {
            if (! in_array($ruta, $guardadas, true)) {
                return false;
            }
        }

        $resumen = fn (mixed $actividades): array => collect((array) $actividades)
            ->map(fn ($a) => [
                'descripcion_orden' => $this->canonico($a['descripcion_orden'] ?? null),
                'actividades' => $this->canonico($a['actividades'] ?? null),
            ])->values()->all();

        if ($resumen($data['actividades'] ?? []) !== $resumen($reporte->actividades ?? [])) {
            return false;
        }

        if (! $esFraudes) {
            return $this->canonico($this->extraerDatosServicio($data)) === $this->canonico($reporte->datos_servicio ?? []);
        }

        $campos = ['numero_medidor', 'coincide_medidor', 'lectura', 'marca_regulador_id', 'tipo_regulador_id',
            'marca_medidor_id', 'tipo_notificacion_alerta_id', 'tipo_imposibilidad_id'];

        foreach ($campos as $campo) {
            if ($this->canonico($data[$campo] ?? null) !== $this->canonico($reporte->{$campo})) {
                return false;
            }
        }

        foreach (['hay_fuga_gas', 'excede_capacidad'] as $campo) {
            if ($this->canonico($data[$campo] ?? false) !== $this->canonico($reporte->{$campo} ?? false)) {
                return false;
            }
        }

        $reporte->loadMissing('comercio');

        return $this->canonico($data['anomalia'] ?? []) === $this->canonico($reporte->anomalia ?? [])
            && $this->canonico($data['nombre_comercio'] ?? null) === $this->canonico($reporte->comercio?->nombre_comercio)
            && $this->canonico($data['tipo_comercio_id'] ?? null) === $this->canonico($reporte->comercio?->tipo_comercio_id);
    }

    /** Forma canónica para comparar valores sin importar tipo (null/''/bool/número) ni orden de claves. */
    private function canonico(mixed $valor): mixed
    {
        if (is_array($valor)) {
            if ($valor === []) {
                return '';
            }

            $items = array_map(fn ($v) => $this->canonico($v), $valor);

            if (array_is_list($valor)) {
                if (collect($items)->every(fn ($v) => is_string($v))) {
                    sort($items);
                }

                return $items;
            }

            ksort($items);

            return $items;
        }

        if ($valor === null || $valor === '') {
            return '';
        }

        if (is_bool($valor)) {
            return $valor ? '1' : '0';
        }

        return (string) $valor;
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
                if (! is_array($item)) {
                    continue;
                }

                if (empty($item['uuid'])) {
                    $data['actividades'][$i]['uuid'] = (string) Str::uuid();
                }

                // La app móvil omite la clave cuando no hay respuesta; las pantallas de revisión la esperan.
                $data['actividades'][$i]['actividades'] = (string) ($item['actividades'] ?? '');
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
