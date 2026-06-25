<div class="space-y-6">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Revisar Auditoría</flux:heading>
            <flux:text class="mt-1 flex flex-wrap items-center gap-2 text-sm">
                <span>Orden: <span class="font-semibold">{{ $orden?->numero_orden }}</span></span>
                @if ($orden?->tipo_servicio)
                    <flux:badge color="{{ $orden->tipo_servicio->color() }}" size="sm">
                        {{ $orden->tipo_servicio->label() }}
                    </flux:badge>
                @endif
                @if ($auditoria->estado === \App\Enums\EstadoAuditoria::PendienteAuditoria)
                    <flux:badge color="warning">Pendiente por auditar</flux:badge>
                @elseif ($auditoria->estado === \App\Enums\EstadoAuditoria::Confirmado)
                    <flux:badge color="green">Confirmado</flux:badge>
                @else
                    <flux:badge color="red">Negativo</flux:badge>
                @endif
            </flux:text>
        </div>
        <div class="flex items-center gap-3">
            @if ($auditoria->estado === \App\Enums\EstadoAuditoria::PendienteAuditoria)
                <flux:modal.trigger name="registrar-auditoria">
                    <flux:button variant="primary" icon="check-circle">Registrar Resultado</flux:button>
                </flux:modal.trigger>
            @endif
            <flux:button href="{{ $this->volverUrl() }}" icon="arrow-left" variant="ghost" wire:navigate>
                Volver
            </flux:button>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        {{-- Columna principal: reporte del investigador --}}
        <div class="space-y-6 lg:col-span-2">

            {{-- ═══ FRAUDES ═══ --}}
            @if ($orden?->tipo_servicio === \App\Enums\TipoServicio::Fraudes)

                {{-- Comercio Visitado --}}
                <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                        Comercio Visitado</h3>
                    <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Nombre del Comercio</dt>
                            <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">
                                {{ $reporte?->comercio?->nombre_comercio ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Tipo de Comercio</dt>
                            <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">
                                {{ $reporte?->comercio?->tipoComercio?->nombre ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Contrato</dt>
                            <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">
                                {{ $orden?->contrato ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Dirección</dt>
                            <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">
                                {{ $orden?->direccion }}{{ $orden?->barrio ? ', ' . $orden->barrio : '' }}</dd>
                        </div>
                    </dl>
                </div>

                {{-- Datos de la Visita --}}
                <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                        Datos de la Visita</h3>
                    <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Lectura del Medidor</dt>
                            <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">
                                {{ $reporte?->lectura ?? '—' }}</dd>
                        </div>
                        @if ($reporte?->numero_medidor)
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Número de Medidor</dt>
                                <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">
                                    {{ $reporte->numero_medidor }}</dd>
                            </div>
                        @endif
                        @if ($reporte?->tipoNotificacionAlerta)
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Notificación de Alerta</dt>
                                <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">
                                    {{ $reporte->tipoNotificacionAlerta->nombre }}</dd>
                            </div>
                        @endif
                        @if ($reporte?->hay_fuga_gas || $reporte?->excede_capacidad)
                            <div class="sm:col-span-2">
                                <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Incidencias</dt>
                                <dd class="mt-1 flex flex-wrap gap-1">
                                    @if ($reporte->hay_fuga_gas)
                                        <flux:badge color="red" size="sm">Fuga de Gas</flux:badge>
                                    @endif
                                    @if ($reporte->excede_capacidad)
                                        <flux:badge color="orange" size="sm">Excede Capacidad</flux:badge>
                                    @endif
                                </dd>
                            </div>
                        @endif
                        @if (!empty($reporte?->anomalia))
                            <div class="sm:col-span-2">
                                <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Anomalías</dt>
                                <dd class="mt-1 flex flex-wrap gap-1">
                                    @foreach ($reporte->anomalia as $anomaliaId)
                                        @php $tipo = \App\Models\TipoAnomalia::find($anomaliaId) @endphp
                                        @if ($tipo)
                                            <flux:badge color="yellow" size="sm">{{ $tipo->nombre }}</flux:badge>
                                        @endif
                                    @endforeach
                                </dd>
                            </div>
                        @endif
                    </dl>
                </div>

                {{-- Clasificación Técnica --}}
                @if ($reporte?->tipoImposibilidad || $reporte?->tipoRegulador || $reporte?->marcaMedidor || $reporte?->marcaRegulador)
                    <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                        <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                            Clasificación Técnica</h3>
                        <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
                            @if ($reporte->tipoImposibilidad)
                                <div>
                                    <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Tipo de Imposibilidad</dt>
                                    <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">
                                        {{ $reporte->tipoImposibilidad->nombre }}</dd>
                                </div>
                            @endif
                            @if ($reporte->tipoRegulador)
                                <div>
                                    <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Tipo de Presión</dt>
                                    <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">
                                        {{ $reporte->tipoRegulador->nombre }}</dd>
                                </div>
                            @endif
                            @if ($reporte->marcaMedidor)
                                <div>
                                    <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Marca del Medidor</dt>
                                    <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">
                                        {{ $reporte->marcaMedidor->nombre }}</dd>
                                </div>
                            @endif
                            @if ($reporte->marcaRegulador)
                                <div>
                                    <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Descripción del Medidor</dt>
                                    <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">
                                        {{ $reporte->marcaRegulador->nombre }}</dd>
                                </div>
                            @endif
                        </dl>
                    </div>
                @endif

            {{-- ═══ INVESTIGACIÓN ═══ --}}
            @elseif ($orden?->tipo_servicio === \App\Enums\TipoServicio::Investigacion)

                <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                        Datos de la Investigación</h3>
                    <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
                        @if ($orden->tipo_investigacion)
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Tipo de Investigación</dt>
                                <dd class="mt-0.5">
                                    <flux:badge color="blue" size="sm">{{ $orden->tipo_investigacion }}</flux:badge>
                                </dd>
                            </div>
                        @endif
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Convención</dt>
                            <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">
                                {{ $datosServicio['convencion'] ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Acompañamiento Técnico</dt>
                            <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">
                                {{ ($datosServicio['acompanamiento_tecnico'] ?? false) ? 'Sí' : 'No' }}</dd>
                        </div>
                        @if ($datosServicio['resultado'] ?? null)
                            <div class="sm:col-span-2">
                                <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Resultado</dt>
                                <dd class="mt-0.5">
                                    <flux:badge color="blue" size="sm">{{ $datosServicio['resultado'] }}</flux:badge>
                                </dd>
                            </div>
                        @endif
                    </dl>
                </div>

                @if ($datosServicio['acompanamiento_tecnico'] ?? false)
                    <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                        <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                            Acompañante Técnico</h3>
                        <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
                            @if ($datosServicio['acompanante_nombre'] ?? null)
                                <div>
                                    <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Nombre</dt>
                                    <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">
                                        {{ $datosServicio['acompanante_nombre'] }}</dd>
                                </div>
                            @endif
                            @if ($datosServicio['acompanante_identificacion'] ?? null)
                                <div>
                                    <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Identificación</dt>
                                    <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">
                                        {{ $datosServicio['acompanante_identificacion'] }}</dd>
                                </div>
                            @endif
                            @if ($datosServicio['acompanante_cargo'] ?? null)
                                <div>
                                    <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Cargo / Empresa</dt>
                                    <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">
                                        {{ $datosServicio['acompanante_cargo'] }}</dd>
                                </div>
                            @endif
                            @if ($datosServicio['acompanante_cuadrilla'] ?? null)
                                <div>
                                    <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Código de Cuadrilla</dt>
                                    <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">
                                        {{ $datosServicio['acompanante_cuadrilla'] }}</dd>
                                </div>
                            @endif
                        </dl>
                    </div>
                @endif

                @if ($datosServicio['tipo_investigado'] ?? null)
                    <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                        <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                            {{ $datosServicio['tipo_investigado'] === 'persona' ? 'Persona Investigada' : 'Comercio Investigado' }}
                        </h3>
                        <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
                            @if ($datosServicio['tipo_investigado'] === 'persona')
                                @if ($datosServicio['nombre_investigado'] ?? null)
                                    <div>
                                        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Nombre</dt>
                                        <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">
                                            {{ $datosServicio['nombre_investigado'] }}</dd>
                                    </div>
                                @endif
                                @if ($datosServicio['identificacion'] ?? null)
                                    <div>
                                        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Identificación</dt>
                                        <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">
                                            {{ $datosServicio['identificacion'] }}</dd>
                                    </div>
                                @endif
                                @if ($datosServicio['descripcion_fisica'] ?? null)
                                    <div class="sm:col-span-2">
                                        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Descripción Física</dt>
                                        <dd class="mt-0.5 whitespace-pre-line text-sm text-zinc-700 dark:text-zinc-300">
                                            {{ $datosServicio['descripcion_fisica'] }}</dd>
                                    </div>
                                @endif
                            @else
                                @if ($datosServicio['nombre_comercio_inv'] ?? null)
                                    <div>
                                        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Nombre del Comercio</dt>
                                        <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">
                                            {{ $datosServicio['nombre_comercio_inv'] }}</dd>
                                    </div>
                                @endif
                                @if ($datosServicio['tipo_comercio_inv'] ?? null)
                                    <div>
                                        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Tipo de Comercio</dt>
                                        <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">
                                            {{ $datosServicio['tipo_comercio_inv'] }}</dd>
                                    </div>
                                @endif
                            @endif
                        </dl>
                    </div>
                @endif

            {{-- ═══ GESTIÓN LEGAL ═══ --}}
            @elseif ($orden?->tipo_servicio === \App\Enums\TipoServicio::GestionLegal)

                <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                        Gestión Legal</h3>
                    <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
                        @if ($datosServicio['tipo_gestion_label'] ?? null)
                            <div class="sm:col-span-2">
                                <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Tipo de Gestión</dt>
                                <dd class="mt-0.5">
                                    <flux:badge color="blue" size="sm">{{ $datosServicio['tipo_gestion_label'] }}</flux:badge>
                                </dd>
                            </div>
                        @endif
                        @if ($datosServicio['numero_noticia_criminal'] ?? null)
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">N° Noticia Criminal</dt>
                                <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">
                                    {{ $datosServicio['numero_noticia_criminal'] }}</dd>
                            </div>
                        @endif
                        @if ($datosServicio['tipologia'] ?? null)
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Tipología</dt>
                                <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">
                                    {{ $datosServicio['tipologia'] }}</dd>
                            </div>
                        @endif
                        @if ($datosServicio['fecha_ejecucion'] ?? null)
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Fecha de Ejecución</dt>
                                <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">
                                    {{ \Illuminate\Support\Carbon::parse($datosServicio['fecha_ejecucion'])->format('d/m/Y') }}
                                </dd>
                            </div>
                        @endif
                        @if ($datosServicio['departamento'] ?? null)
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Departamento</dt>
                                <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">
                                    {{ $datosServicio['departamento'] }}</dd>
                            </div>
                        @endif
                        @if ($datosServicio['municipio'] ?? null)
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Municipio</dt>
                                <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">
                                    {{ $datosServicio['municipio'] }}</dd>
                            </div>
                        @endif
                        @if ($datosServicio['radio_objetivo'] ?? null)
                            <div class="sm:col-span-2">
                                <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Radio del Objetivo</dt>
                                <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">
                                    {{ $datosServicio['radio_objetivo'] }}</dd>
                            </div>
                        @endif
                        @if ($datosServicio['resultado'] ?? null)
                            <div class="sm:col-span-2">
                                <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Resultado</dt>
                                <dd class="mt-0.5">
                                    <flux:badge color="blue" size="sm">{{ $datosServicio['resultado'] }}</flux:badge>
                                </dd>
                            </div>
                        @endif
                    </dl>
                </div>

            {{-- ═══ VIGILANCIA ═══ --}}
            @elseif ($orden?->tipo_servicio === \App\Enums\TipoServicio::Vigilancia)

                <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Turno</h3>
                    <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-3">
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Fecha</dt>
                            <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">
                                {{ $datosServicio['fecha_turno'] ? \Illuminate\Support\Carbon::parse($datosServicio['fecha_turno'])->format('d/m/Y') : '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Hora Inicio</dt>
                            <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">
                                {{ $datosServicio['hora_inicio'] ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Hora Fin</dt>
                            <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">
                                {{ $datosServicio['hora_fin'] ?? '—' }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Novedades</h3>
                    @if ($datosServicio['hay_novedad'] ?? false)
                        <div class="space-y-3">
                            <div class="flex gap-2">
                                <flux:badge color="yellow" size="sm">Con Novedades</flux:badge>
                                @if ($datosServicio['hay_reincidencia'] ?? false)
                                    <flux:badge color="red" size="sm">Reincidencia Detectada</flux:badge>
                                @endif
                            </div>
                            @if ($datosServicio['descripcion_novedad'] ?? null)
                                <p class="whitespace-pre-line text-sm text-zinc-700 dark:text-zinc-300">
                                    {{ $datosServicio['descripcion_novedad'] }}</p>
                            @endif
                        </div>
                    @else
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">Sin novedades durante el turno.</p>
                    @endif
                </div>

                @if ($datosServicio['resultado'] ?? null)
                    <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                            Resultado del Turno</h3>
                        <flux:badge color="blue" size="sm">{{ $datosServicio['resultado'] }}</flux:badge>
                    </div>
                @endif

            {{-- ═══ AUDITORÍA DE CAMPO ═══ --}}
            @elseif ($orden?->tipo_servicio === \App\Enums\TipoServicio::AuditoriaCampo)

                <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <h3 class="mb-1 text-sm font-semibold uppercase tracking-wide text-zinc-500">Auditoría de Campo</h3>
                            <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ $datosServicio['subtipo'] ?? '—' }}</p>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase text-zinc-400">Fecha y Hora de Atención</dt>
                            <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">
                                {{ $datosServicio['fecha_hora_atencion'] ?? '—' }}</dd>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500">1. Información General</h3>
                    <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm sm:grid-cols-3">
                        <div>
                            <dt class="text-xs font-medium uppercase text-zinc-400">OT</dt>
                            <dd class="mt-0.5 font-medium text-zinc-900 dark:text-white">{{ $orden->numero_orden }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase text-zinc-400">Contrato</dt>
                            <dd class="mt-0.5 font-medium text-zinc-900 dark:text-white">{{ $orden->contrato ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase text-zinc-400">Usuario</dt>
                            <dd class="mt-0.5 font-medium text-zinc-900 dark:text-white">{{ $orden->cliente ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase text-zinc-400">Nombre del Técnico</dt>
                            <dd class="mt-0.5 font-medium text-zinc-900 dark:text-white">{{ $orden->tecnico ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase text-zinc-400">Dirección</dt>
                            <dd class="mt-0.5 font-medium text-zinc-900 dark:text-white">{{ $orden->direccion ?? '—' }}</dd>
                        </div>
                        @if ($datosServicio['ciudad'] ?? null)
                            <div>
                                <dt class="text-xs font-medium uppercase text-zinc-400">Ciudad</dt>
                                <dd class="mt-0.5 font-medium text-zinc-900 dark:text-white">{{ $datosServicio['ciudad'] }}</dd>
                            </div>
                        @endif
                        @if ($datosServicio['comentario_usuario'] ?? null)
                            <div class="col-span-full">
                                <dt class="text-xs font-medium uppercase text-zinc-400">Solicitud del Usuario</dt>
                                <dd class="mt-0.5 text-zinc-700 dark:text-zinc-300">{{ $datosServicio['comentario_usuario'] }}</dd>
                            </div>
                        @endif
                        @if ($datosServicio['comentario_tecnico'] ?? null)
                            <div class="col-span-full">
                                <dt class="text-xs font-medium uppercase text-zinc-400">Comentario Técnico</dt>
                                <dd class="mt-0.5 text-zinc-700 dark:text-zinc-300">{{ $datosServicio['comentario_tecnico'] }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>

                <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500">2. Quién Atendió la Visita</h3>
                    <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm sm:grid-cols-3">
                        <div>
                            <dt class="text-xs font-medium uppercase text-zinc-400">Nombre</dt>
                            <dd class="mt-0.5 font-medium text-zinc-900 dark:text-white">{{ $datosServicio['nombre_atiende'] ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase text-zinc-400">Vínculo</dt>
                            <dd class="mt-0.5 font-medium text-zinc-900 dark:text-white">{{ $datosServicio['vinculo_usuario'] ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase text-zinc-400">Celular</dt>
                            <dd class="mt-0.5 font-medium text-zinc-900 dark:text-white">{{ $datosServicio['celular_atiende'] ?? '—' }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500">3–6. Validación y Hallazgos</h3>
                    <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm sm:grid-cols-3">
                        @php $yesno = fn($v) => $v ? 'Sí' : 'No'; @endphp
                        <div>
                            <dt class="text-xs font-medium uppercase text-zinc-400">Recibió visita</dt>
                            <dd class="mt-0.5 font-medium text-zinc-900 dark:text-white">{{ $yesno($datosServicio['recibio_visita']) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase text-zinc-400">Se realizó revisión</dt>
                            <dd class="mt-0.5 font-medium text-zinc-900 dark:text-white">{{ $yesno($datosServicio['se_realizo_revision']) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase text-zinc-400">Fecha ejecución</dt>
                            <dd class="mt-0.5 font-medium text-zinc-900 dark:text-white">{{ $datosServicio['fecha_ejecucion'] ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase text-zinc-400">Tiempo ejecución</dt>
                            <dd class="mt-0.5 font-medium text-zinc-900 dark:text-white">{{ $datosServicio['tiempo_ejecucion'] ?? '—' }}</dd>
                        </div>
                        @if ($datosServicio['descripcion_trabajo'] ?? null)
                            <div class="col-span-full">
                                <dt class="text-xs font-medium uppercase text-zinc-400">Trabajo realizado</dt>
                                <dd class="mt-0.5 text-zinc-700 dark:text-zinc-300">{{ $datosServicio['descripcion_trabajo'] }}</dd>
                            </div>
                        @endif
                        <div>
                            <dt class="text-xs font-medium uppercase text-zinc-400">Fuga / falla detectada</dt>
                            <dd class="mt-0.5 font-medium text-zinc-900 dark:text-white">
                                {{ $yesno($datosServicio['se_detecto_fuga']) }}{{ $datosServicio['ppm'] ?? null ? ' · PPM: ' . $datosServicio['ppm'] : '' }}{{ $datosServicio['vol_porcentaje'] ?? null ? ' · VOL%: ' . $datosServicio['vol_porcentaje'] : '' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase text-zinc-400">Problema persiste</dt>
                            <dd class="mt-0.5 font-medium text-zinc-900 dark:text-white">{{ $yesno($datosServicio['problema_persiste']) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase text-zinc-400">Evidencia reparación</dt>
                            <dd class="mt-0.5 font-medium text-zinc-900 dark:text-white">{{ $yesno($datosServicio['evidencia_reparacion']) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase text-zinc-400">Servicio correcto</dt>
                            <dd class="mt-0.5 font-medium text-zinc-900 dark:text-white">{{ $yesno($datosServicio['servicio_correcto']) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase text-zinc-400">Se usaron materiales</dt>
                            <dd class="mt-0.5 font-medium text-zinc-900 dark:text-white">
                                {{ $yesno($datosServicio['se_usaron_materiales']) }}{{ $datosServicio['quien_suministro_materiales'] ?? null ? ' · ' . $datosServicio['quien_suministro_materiales'] : '' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase text-zinc-400">Usuario asumió costo</dt>
                            <dd class="mt-0.5 font-medium text-zinc-900 dark:text-white">
                                {{ $yesno($datosServicio['usuario_asumio_costo']) }}{{ $datosServicio['valor_pagado'] ?? null ? ' · $' . $datosServicio['valor_pagado'] . ' (' . ($datosServicio['medio_pago'] ?? '—') . ')' : '' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase text-zinc-400">Entregó factura/IVT</dt>
                            <dd class="mt-0.5 font-medium text-zinc-900 dark:text-white">{{ $yesno($datosServicio['entrego_factura']) }}</dd>
                        </div>
                        @if ($datosServicio['tecnico_dejo_documento'] ?? null)
                            <div class="col-span-full">
                                <dt class="text-xs font-medium uppercase text-zinc-400">Documento dejado</dt>
                                <dd class="mt-0.5 text-zinc-700 dark:text-zinc-300">{{ $datosServicio['tecnico_dejo_documento'] }}</dd>
                            </div>
                        @endif
                        <div>
                            <dt class="text-xs font-medium uppercase text-zinc-400">Informaron que no estaba</dt>
                            <dd class="mt-0.5 font-medium text-zinc-900 dark:text-white">{{ $yesno($datosServicio['informaron_no_estaba']) }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500">7. Autorización y Firmas</h3>
                    <p class="mb-4 text-sm"><span class="font-medium">Autoriza:</span>
                        {{ ($datosServicio['autoriza'] ?? false) ? 'SÍ' : 'NO' }}</p>
                    <div class="grid grid-cols-2 gap-6">
                        <div>
                            <p class="mb-1 text-xs font-medium uppercase text-zinc-400">Firma del Usuario</p>
                            @if ($datosServicio['firma_usuario'] ?? null)
                                <img src="{{ $datosServicio['firma_usuario'] }}" alt="Firma usuario"
                                    class="rounded border border-zinc-200 bg-white dark:border-zinc-600"
                                    style="max-height:120px;">
                            @else
                                <p class="text-sm text-zinc-400">Sin firma</p>
                            @endif
                            <p class="mt-1 text-xs text-zinc-500">Cédula: {{ $datosServicio['cedula_usuario'] ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="mb-1 text-xs font-medium uppercase text-zinc-400">Firma del Auditor</p>
                            @if ($datosServicio['firma_auditor'] ?? null)
                                <img src="{{ $datosServicio['firma_auditor'] }}" alt="Firma auditor"
                                    class="rounded border border-zinc-200 bg-white dark:border-zinc-600"
                                    style="max-height:120px;">
                            @else
                                <p class="text-sm text-zinc-400">Sin firma</p>
                            @endif
                            <p class="mt-1 text-xs text-zinc-500">Cédula: {{ $datosServicio['cedula_auditor'] ?? '—' }}</p>
                        </div>
                    </div>
                </div>

                @if (!empty($datosServicio['urls_evidencia']))
                    <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-500">URLs de Evidencia / Documentos</h3>
                        <ul class="space-y-1">
                            @foreach ($datosServicio['urls_evidencia'] as $url)
                                <li>
                                    <a href="{{ $url }}" target="_blank" rel="noopener"
                                        class="break-all text-sm text-blue-600 underline hover:text-blue-800 dark:text-blue-400">
                                        {{ $url }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

            @endif
            {{-- ═══ FIN secciones por servicio ═══ --}}

            {{-- Actividades (común para todos los servicios) --}}
            @if (!empty($reporte?->actividades) && is_array($reporte->actividades))
                <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                        Actividades Realizadas</h3>
                    <ul class="space-y-4">
                        @foreach ($reporte->actividades as $actividad)
                            <li class="border-b border-zinc-100 pb-4 last:border-b-0 last:pb-0 dark:border-zinc-800">
                                @if (!empty($actividad['descripcion_orden']))
                                    <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-zinc-400 dark:text-zinc-500">
                                        Actividad asignada</p>
                                    <p class="mb-2 text-sm text-zinc-500 dark:text-zinc-400">{{ $actividad['descripcion_orden'] }}</p>
                                    <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-zinc-400 dark:text-zinc-500">
                                        Respuesta del investigador</p>
                                @endif
                                <div class="flex items-start gap-3">
                                    <div class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-blue-500 text-xs font-bold text-white">
                                        ✓
                                    </div>
                                    <p class="wrap-break-word text-sm text-zinc-900 dark:text-white">{{ $actividad['actividades'] }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Observaciones del investigador --}}
            @if ($reporte?->observaciones)
                <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                        Observaciones del Investigador</h3>
                    <p class="whitespace-pre-line text-sm text-zinc-700 dark:text-zinc-300">{{ $reporte->observaciones }}</p>
                </div>
            @endif

               {{-- Conclusión del Coordinador --}}
            @if ($reporte?->conclusion)
                <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                        Conclusión del Coordinador</h3>
                    <p class="whitespace-pre-line text-sm text-zinc-700 dark:text-zinc-300">{{ $reporte->conclusion }}</p>
                </div>
            @endif

            {{-- Evidencias del investigador --}}
            @if ($orden?->tipo_servicio === \App\Enums\TipoServicio::Gestion_Riesgo)
                @if (!empty($archivosUrls))
                    @php
                        $imgs = array_values(array_filter($archivosUrls, fn($a) => $a['es_imagen']));
                        $docs = array_values(array_filter($archivosUrls, fn($a) => !$a['es_imagen']));
                    @endphp
                    <div x-data="{ open: false, src: '' }" @keydown.escape.window="open = false"
                        class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                        <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                            Evidencias del Investigador ({{ count($archivosUrls) }}
                            {{ count($archivosUrls) === 1 ? 'archivo' : 'archivos' }})</h3>

                        @if (!empty($imgs))
                            <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
                                @foreach ($imgs as $archivo)
                                    <button type="button" @click="src = '{{ $archivo['url'] }}'; open = true"
                                        class="block overflow-hidden rounded-lg border border-zinc-100 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-zinc-700">
                                        <img src="{{ $archivo['url'] }}" alt="{{ $archivo['nombre'] }}"
                                            class="h-40 w-full object-cover transition hover:opacity-80">
                                    </button>
                                @endforeach
                            </div>
                        @endif

                        @if (!empty($docs))
                            <ul class="space-y-2">
                                @foreach ($docs as $archivo)
                                    <li class="flex items-center gap-3 rounded-lg border border-zinc-100 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-800">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 shrink-0 text-zinc-400"
                                            fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                        </svg>
                                        <span class="flex-1 truncate text-sm text-zinc-700 dark:text-zinc-300">{{ $archivo['nombre'] }}</span>
                                        <a href="{{ $archivo['url'] }}" target="_blank" rel="noopener"
                                            class="inline-flex items-center gap-1.5 rounded-md bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none"
                                                viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                            </svg>
                                            Abrir
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        <div x-show="open" x-transition.opacity @click.self="open = false"
                            class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4" style="display:none">
                            <div class="relative max-h-full max-w-4xl">
                                <button type="button" @click="open = false"
                                    class="absolute -right-3 -top-3 flex h-8 w-8 items-center justify-center rounded-full bg-white text-zinc-800 shadow-lg hover:bg-zinc-100">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                                        stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                                <img :src="src" alt="Foto ampliada"
                                    class="max-h-[85vh] max-w-full rounded-lg object-contain shadow-2xl">
                            </div>
                        </div>
                    </div>
                @endif
            @elseif (!empty($imagenUrls))
                <div x-data="{ open: false, src: '' }" @keydown.escape.window="open = false"
                    class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                        Evidencias del Investigador ({{ count($imagenUrls) }} fotos)</h3>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        @foreach ($imagenUrls as $url)
                            <button type="button" @click="src = '{{ $url }}'; open = true"
                                class="overflow-hidden rounded-lg border border-zinc-100 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-zinc-700">
                                <img src="{{ $url }}" alt="Evidencia" class="h-40 w-full object-cover transition hover:opacity-80">
                            </button>
                        @endforeach
                    </div>
                    <div x-show="open" x-transition.opacity @click.self="open = false"
                        class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4" style="display:none">
                        <div class="relative max-h-full max-w-4xl">
                            <button type="button" @click="open = false"
                                class="absolute -right-3 -top-3 flex h-8 w-8 items-center justify-center rounded-full bg-white text-zinc-800 shadow-lg hover:bg-zinc-100">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                            <img :src="src" alt="Foto ampliada"
                                class="max-h-[85vh] max-w-full rounded-lg object-contain shadow-2xl">
                        </div>
                    </div>
                </div>
            @endif

            {{-- Video --}}
            @if ($reporte?->video)
                <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Video</h3>
                    <a href="{{ $reporte->video }}" target="_blank" rel="noopener"
                        class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition-all hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:bg-blue-500 dark:hover:bg-blue-600">
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                            <path d="M6.3 2.841A1.5 1.5 0 004 4.11v11.78a1.5 1.5 0 002.3 1.269l9.344-5.89a1.5 1.5 0 000-2.538L6.3 2.84z"></path>
                        </svg>
                        Ver video
                    </a>
                </div>
            @endif

        </div>

        {{-- Columna lateral --}}
        <div class="space-y-6">

            {{-- Información de la orden --}}
            @if ($orden?->cliente || $orden?->direccion || $orden?->numero_noticia_criminal)
                <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Orden</h3>
                    <dl class="space-y-2">
                        @if ($orden->cliente)
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Cliente / Referencia</dt>
                                <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $orden->cliente }}</dd>
                            </div>
                        @endif
                        @if ($orden->direccion)
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Dirección</dt>
                                <dd class="mt-0.5 text-sm text-zinc-900 dark:text-white">{{ $orden->direccion }}</dd>
                            </div>
                        @endif
                        @if ($orden->numero_noticia_criminal)
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">N° Noticia Criminal</dt>
                                <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">
                                    {{ $orden->numero_noticia_criminal }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            @endif

            {{-- Investigador --}}
            @php
                $responsable = $orden?->tipo_servicio === \App\Enums\TipoServicio::Vigilancia
                    ? $reporte?->personal
                    : $orden?->personal;
            @endphp
            <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Investigador</h3>
                <dl class="space-y-2">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Nombre</dt>
                        <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">
                            {{ $responsable ? "{$responsable->nombres} {$responsable->apellidos}" : '—' }}
                        </dd>
                    </div>
                    @if ($responsable?->celular)
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Teléfono</dt>
                            <dd class="mt-0.5 text-sm text-zinc-900 dark:text-white">{{ $responsable->celular }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Fecha Completado</dt>
                        <dd class="mt-0.5 text-sm text-zinc-900 dark:text-white">
                            {{ $orden?->fecha_completado?->format('d/m/Y H:i') ?? '—' }}
                        </dd>
                    </div>
                </dl>
            </div>

            {{-- Ubicación GPS --}}
            @if ($reporte?->ubicacion?->latitud && $reporte?->ubicacion?->longitud)
                <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Ubicación GPS</h3>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">
                        {{ number_format((float) $reporte->ubicacion->latitud, 6) }},
                        {{ number_format((float) $reporte->ubicacion->longitud, 6) }}
                    </p>
                    <a href="https://www.google.com/maps?q={{ $reporte->ubicacion->latitud }},{{ $reporte->ubicacion->longitud }}"
                        target="_blank" rel="noopener"
                        class="mt-2 inline-flex items-center gap-1 text-sm text-blue-600 underline hover:text-blue-800 dark:text-blue-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Ver en Google Maps
                    </a>
                </div>
            @endif

            {{-- Resultado de auditoría (solo si ya fue procesada) --}}
            @if ($auditoria->estado !== \App\Enums\EstadoAuditoria::PendienteAuditoria)
                <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                        Resultado de la Auditoría</h3>
                    <dl class="space-y-4">
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Resultado</dt>
                            <dd class="mt-1">
                                @if ($auditoria->estado === \App\Enums\EstadoAuditoria::Confirmado)
                                    <flux:badge color="green">Confirmado</flux:badge>
                                @else
                                    <flux:badge color="red">Negativo</flux:badge>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Fecha de Auditoría</dt>
                            <dd class="mt-0.5 text-sm text-zinc-900 dark:text-white">
                                {{ $auditoria->fecha_auditoria?->format('d/m/Y H:i') ?? '—' }}
                            </dd>
                        </div>
                        @if ($auditoria->auditadoPor)
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Auditado por</dt>
                                <dd class="mt-0.5 text-sm text-zinc-900 dark:text-white">
                                    {{ $auditoria->auditadoPor->fullName() }}
                                </dd>
                            </div>
                        @endif
                        @if ($auditoria->tipoAnomalia)
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Tipo de Anomalía</dt>
                                <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">
                                    {{ $auditoria->tipoAnomalia->nombre }}</dd>
                            </div>
                        @endif
                        @if ($auditoria->metros_cubicos_recuperados !== null)
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">M³ Recuperados</dt>
                                <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">
                                    {{ number_format((float) $auditoria->metros_cubicos_recuperados, 4) }}</dd>
                            </div>
                        @endif
                        @if ($auditoria->observaciones)
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Observaciones</dt>
                                <dd class="mt-0.5 whitespace-pre-line text-sm text-zinc-700 dark:text-zinc-300">
                                    {{ $auditoria->observaciones }}</dd>
                            </div>
                        @endif
                        @if (!empty($auditoria->evidencias))
                            <div>
                                <dt class="mb-2 text-xs font-medium uppercase tracking-wide text-zinc-400">
                                    Evidencias ({{ count($auditoria->evidencias) }})</dt>
                                <dd class="space-y-2">
                                    @foreach ($auditoria->evidencias as $evidencia)
                                        <a href="{{ route('operaciones.foto', ['path' => $evidencia]) }}"
                                            target="_blank"
                                            class="flex items-center gap-2 rounded-lg border border-zinc-200 px-3 py-2 text-sm text-blue-600 hover:bg-zinc-50 dark:border-zinc-700 dark:text-blue-400 dark:hover:bg-zinc-800">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none"
                                                viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                            </svg>
                                            <span class="truncate">{{ basename($evidencia) }}</span>
                                        </a>
                                    @endforeach
                                </dd>
                            </div>
                        @endif
                    </dl>
                </div>
            @endif

        </div>
    </div>

    {{-- Modal: Registrar Auditoría --}}
    <flux:modal name="registrar-auditoria" class="w-full max-w-lg">
        <div class="space-y-5">
            <form wire:submit="guardar" class="space-y-4">
                {{ $this->form }}
                <flux:button type="submit" variant="primary" icon="check-circle" class="w-full justify-center"
                    wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="guardar">Guardar Auditoría</span>
                    <span wire:loading wire:target="guardar">Guardando...</span>
                </flux:button>
            </form>
        </div>
    </flux:modal>

    <x-filament-actions::modals />
</div>
