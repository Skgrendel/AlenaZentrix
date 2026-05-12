<div class="space-y-6">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Revisar Reporte</flux:heading>
            <flux:text class="mt-1 text-sm flex items-center gap-2 flex-wrap">
                <span>Orden: <span class="font-semibold">{{ $orden->numero_orden }}</span></span>
                <flux:badge color="{{ $orden->tipo_servicio->color() }}" size="sm">{{ $orden->tipo_servicio->label() }}</flux:badge>
                @if($orden->estado === \App\Enums\EstadoOrden::Completada)
                    <flux:badge color="green" class="ml-1">Completada</flux:badge>
                @else
                    <flux:badge color="purple" class="ml-1">En Revisión</flux:badge>
                @endif
            </flux:text>
        </div>
        <div class="flex items-center gap-2">
            @if($orden->tipo_servicio === \App\Enums\TipoServicio::Fraudes && auth()->user()->can('informes.fraude'))
                <flux:button
                    href="{{ route('operaciones.ordenes-trabajo.reporte-pdf', [$orden, 'reporte' => $reporte->id]) }}"
                    icon="arrow-down-tray"
                    variant="filled"
                    target="_blank"
                >
                    Descargar PDF
                </flux:button>
            @endif
            <flux:button
                href="{{ $orden->tipo_servicio === \App\Enums\TipoServicio::Vigilancia ? route('operaciones.ordenes-trabajo.reportes', $orden) : route('operaciones.ordenes-trabajo') }}"
                icon="arrow-left"
                variant="ghost"
                wire:navigate
            >
                Volver
            </flux:button>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        {{-- Columna principal --}}
        <div class="space-y-6 lg:col-span-2">

            {{-- ═══ FRAUDES ═══ --}}
            @if($orden->tipo_servicio === \App\Enums\TipoServicio::Fraudes)

                {{-- Comercio Visitado --}}
                <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Comercio Visitado</h3>
                    <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Nombre del Comercio</dt>
                            <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $reporte->comercio?->nombre_comercio ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Tipo de Comercio</dt>
                            <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $reporte->comercio?->tipoComercio?->nombre ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Contrato</dt>
                            <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $orden->contrato ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Dirección</dt>
                            <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $orden->direccion }}{{ $orden->barrio ? ', '.$orden->barrio : '' }}</dd>
                        </div>
                    </dl>
                </div>

                {{-- Datos de la Visita --}}
                <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Datos de la Visita</h3>
                    <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Lectura del Medidor</dt>
                            <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $reporte->lectura ?? '—' }}</dd>
                        </div>
                        @if($reporte->numero_medidor)
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Número de Medidor</dt>
                            <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $reporte->numero_medidor }}</dd>
                        </div>
                        @endif
                        @if($reporte->tipoNotificacionAlerta)
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Notificación de Alerta</dt>
                            <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $reporte->tipoNotificacionAlerta->nombre }}</dd>
                        </div>
                        @endif
                        @if($reporte->hay_fuga_gas || $reporte->excede_capacidad)
                        <div class="sm:col-span-2">
                            <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Incidencias</dt>
                            <dd class="mt-1 flex flex-wrap gap-1">
                                @if($reporte->hay_fuga_gas)
                                    <flux:badge color="red" size="sm">Fuga de Gas</flux:badge>
                                @endif
                                @if($reporte->excede_capacidad)
                                    <flux:badge color="orange" size="sm">Excede Capacidad</flux:badge>
                                @endif
                            </dd>
                        </div>
                        @endif
                        @if(!empty($reporte->anomalia))
                        <div class="sm:col-span-2">
                            <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Anomalías</dt>
                            <dd class="mt-1 flex flex-wrap gap-1">
                                @foreach($reporte->anomalia as $anomaliaId)
                                    @php $tipo = \App\Models\TipoAnomalia::find($anomaliaId) @endphp
                                    @if($tipo)
                                        <flux:badge color="yellow" size="sm">{{ $tipo->nombre }}</flux:badge>
                                    @endif
                                @endforeach
                            </dd>
                        </div>
                        @endif
                    </dl>
                </div>

                {{-- Clasificación Técnica --}}
                @if($reporte->tipoImposibilidad || $reporte->tipoRegulador || $reporte->marcaMedidor || $reporte->marcaRegulador)
                <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Clasificación Técnica</h3>
                    <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
                        @if($reporte->tipoImposibilidad)
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Tipo de Imposibilidad</dt>
                            <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $reporte->tipoImposibilidad->nombre }}</dd>
                        </div>
                        @endif
                        @if($reporte->tipoRegulador)
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Tipo de Presión</dt>
                            <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $reporte->tipoRegulador->nombre }}</dd>
                        </div>
                        @endif
                        @if($reporte->marcaMedidor)
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Marca del Medidor</dt>
                            <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $reporte->marcaMedidor->nombre }}</dd>
                        </div>
                        @endif
                        @if($reporte->marcaRegulador)
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Descripción del Medidor</dt>
                            <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $reporte->marcaRegulador->nombre }}</dd>
                        </div>
                        @endif
                    </dl>
                </div>
                @endif

            {{-- ═══ INVESTIGACIÓN ═══ --}}
            @elseif($orden->tipo_servicio === \App\Enums\TipoServicio::Investigacion)

                <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Datos de la Investigación</h3>
                    <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
                        @if($orden->tipo_investigacion)
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Tipo de Investigación</dt>
                            <dd class="mt-0.5">
                                <flux:badge color="{{ $orden->tipo_investigacion === 'interna' ? 'blue' : 'purple' }}" size="sm">
                                    {{ $orden->tipo_investigacion === 'interna' ? 'Interna' : 'Contratista' }}
                                </flux:badge>
                            </dd>
                        </div>
                        @endif
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Convención</dt>
                            <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $datosServicio['convencion'] ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Acompañamiento Técnico</dt>
                            <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">
                                {{ ($datosServicio['acompanamiento_tecnico'] ?? false) ? 'Sí' : 'No' }}
                            </dd>
                        </div>
                        @if($datosServicio['resultado'] ?? null)
                        <div class="sm:col-span-2">
                            <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Resultado</dt>
                            <dd class="mt-0.5"><flux:badge color="blue" size="sm">{{ $datosServicio['resultado'] }}</flux:badge></dd>
                        </div>
                        @endif
                    </dl>
                </div>

                @if($datosServicio['acompanamiento_tecnico'] ?? false)
                <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Acompañante Técnico</h3>
                    <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
                        @if($datosServicio['acompanante_nombre'] ?? null)
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Nombre</dt>
                            <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $datosServicio['acompanante_nombre'] }}</dd>
                        </div>
                        @endif
                        @if($datosServicio['acompanante_identificacion'] ?? null)
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Identificación</dt>
                            <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $datosServicio['acompanante_identificacion'] }}</dd>
                        </div>
                        @endif
                        @if($datosServicio['acompanante_cargo'] ?? null)
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Cargo / Empresa</dt>
                            <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $datosServicio['acompanante_cargo'] }}</dd>
                        </div>
                        @endif
                        @if($datosServicio['acompanante_cuadrilla'] ?? null)
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Código de Cuadrilla</dt>
                            <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $datosServicio['acompanante_cuadrilla'] }}</dd>
                        </div>
                        @endif
                    </dl>
                </div>
                @endif

                @if($datosServicio['tipo_investigado'] ?? null)
                <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                        {{ $datosServicio['tipo_investigado'] === 'persona' ? 'Persona Investigada' : 'Comercio Investigado' }}
                    </h3>
                    <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
                        @if($datosServicio['tipo_investigado'] === 'persona')
                            @if($datosServicio['nombre_investigado'] ?? null)
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Nombre</dt>
                                <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $datosServicio['nombre_investigado'] }}</dd>
                            </div>
                            @endif
                            @if($datosServicio['identificacion'] ?? null)
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Identificación</dt>
                                <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $datosServicio['identificacion'] }}</dd>
                            </div>
                            @endif
                            @if($datosServicio['descripcion_fisica'] ?? null)
                            <div class="sm:col-span-2">
                                <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Descripción Física</dt>
                                <dd class="mt-0.5 text-sm text-zinc-700 dark:text-zinc-300 whitespace-pre-line">{{ $datosServicio['descripcion_fisica'] }}</dd>
                            </div>
                            @endif
                        @else
                            @if($datosServicio['nombre_comercio_inv'] ?? null)
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Nombre del Comercio</dt>
                                <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $datosServicio['nombre_comercio_inv'] }}</dd>
                            </div>
                            @endif
                            @if($datosServicio['tipo_comercio_inv'] ?? null)
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Tipo de Comercio</dt>
                                <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $datosServicio['tipo_comercio_inv'] }}</dd>
                            </div>
                            @endif
                        @endif
                    </dl>
                </div>
                @endif

            {{-- ═══ CAPTURAS / BANDAS ═══ --}}
            @elseif(in_array($orden->tipo_servicio, [\App\Enums\TipoServicio::Capturas, \App\Enums\TipoServicio::Bandas]))

                <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Datos de la Actividad</h3>
                    <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
                        @if($datosServicio['tipologia'] ?? null)
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Tipología de Orden</dt>
                            <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $datosServicio['tipologia'] }}</dd>
                        </div>
                        @endif
                        @if($datosServicio['fecha_ejecucion'] ?? null)
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Fecha de Ejecución</dt>
                            <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">
                                {{ \Illuminate\Support\Carbon::parse($datosServicio['fecha_ejecucion'])->format('d/m/Y') }}
                            </dd>
                        </div>
                        @endif
                        @if($datosServicio['departamento'] ?? null)
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Departamento</dt>
                            <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $datosServicio['departamento'] }}</dd>
                        </div>
                        @endif
                        @if($datosServicio['municipio'] ?? null)
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Municipio</dt>
                            <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $datosServicio['municipio'] }}</dd>
                        </div>
                        @endif
                        @if($datosServicio['radio_objetivo'] ?? null)
                        <div class="sm:col-span-2">
                            <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Radio del Objetivo</dt>
                            <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $datosServicio['radio_objetivo'] }}</dd>
                        </div>
                        @endif
                    </dl>
                </div>

            {{-- ═══ VIGILANCIA ═══ --}}
            @elseif($orden->tipo_servicio === \App\Enums\TipoServicio::Vigilancia)

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
                            <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $datosServicio['hora_inicio'] ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Hora Fin</dt>
                            <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $datosServicio['hora_fin'] ?? '—' }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Novedades</h3>
                    @if($datosServicio['hay_novedad'] ?? false)
                        <div class="space-y-3">
                            <div class="flex gap-2">
                                <flux:badge color="yellow" size="sm">Con Novedades</flux:badge>
                                @if($datosServicio['hay_reincidencia'] ?? false)
                                    <flux:badge color="red" size="sm">Reincidencia Detectada</flux:badge>
                                @endif
                            </div>
                            @if($datosServicio['descripcion_novedad'] ?? null)
                            <p class="text-sm text-zinc-700 dark:text-zinc-300 whitespace-pre-line">{{ $datosServicio['descripcion_novedad'] }}</p>
                            @endif
                        </div>
                    @else
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">Sin novedades durante el turno.</p>
                    @endif
                </div>

                @if($datosServicio['resultado'] ?? null)
                <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Resultado del Turno</h3>
                    <flux:badge color="blue" size="sm">{{ $datosServicio['resultado'] }}</flux:badge>
                </div>
                @endif

            @endif
            {{-- ═══ FIN secciones por servicio ═══ --}}

            {{-- ═══ Actividades ═══ --}}
            @if(!empty($orden->actividades) && is_array($orden->actividades))
            <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Actividades Realizadas</h3>
                <ul class="space-y-3">
                    @foreach($orden->actividades as $actividad)
                    <li class="flex items-start gap-3 pb-3 border-b border-zinc-100 last:border-b-0 dark:border-zinc-800 last:pb-0">
                        <div class="flex h-5 w-5 items-center justify-center rounded-full bg-blue-500 text-white text-xs font-bold flex-shrink-0 mt-0.5">
                            ✓
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-zinc-900 dark:text-white break-words">{{ $actividad['actividades'] }}</p>
                            @if($actividad['uuid'] ?? null)
                            <p class="mt-1 text-xs text-zinc-400 font-mono">{{ $actividad['uuid'] }}</p>
                            @endif
                        </div>
                    </li>
                    @endforeach
                </ul>
            </div>
            @endif

            {{-- Observaciones (todos los servicios) --}}
            @if($reporte->observaciones)
            <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Observaciones</h3>
                <p class="text-sm text-zinc-700 dark:text-zinc-300 whitespace-pre-line">{{ $reporte->observaciones }}</p>
            </div>
            @endif

            {{-- Evidencias: Imágenes --}}
            @if(! empty($imagenUrls))
            <div
                x-data="{ open: false, src: '' }"
                @keydown.escape.window="open = false"
                class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900"
            >
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Evidencias ({{ count($imagenUrls) }} fotos)</h3>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    @foreach($imagenUrls as $url)
                        <button
                            type="button"
                            @click="src = '{{ $url }}'; open = true"
                            class="block overflow-hidden rounded-lg border border-zinc-100 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-zinc-700"
                        >
                            <img src="{{ $url }}" alt="Evidencia" class="h-40 w-full object-cover transition hover:opacity-80">
                        </button>
                    @endforeach
                </div>

                {{-- Lightbox --}}
                <div
                    x-show="open"
                    x-transition.opacity
                    @click.self="open = false"
                    class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4"
                    style="display: none"
                >
                    <div class="relative max-h-full max-w-4xl">
                        <button
                            type="button"
                            @click="open = false"
                            class="absolute -right-3 -top-3 flex h-8 w-8 items-center justify-center rounded-full bg-white text-zinc-800 shadow-lg hover:bg-zinc-100"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                        <img :src="src" alt="Foto ampliada" class="max-h-[85vh] max-w-full rounded-lg object-contain shadow-2xl">
                    </div>
                </div>
            </div>
            @endif

            @if($reporte->video)
            <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Video</h3>
                <a href="{{ $reporte->video }}" target="_blank" rel="noopener" class="text-sm text-blue-600 underline hover:text-blue-800 dark:text-blue-400">
                    {{ $reporte->video }}
                </a>
            </div>
            @endif

        </div>

        {{-- Columna lateral --}}
        <div class="space-y-6">

            {{-- Información de la Orden --}}
            <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Orden</h3>
                <dl class="space-y-2">
                    @if($orden->cliente)
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Cliente / Referencia</dt>
                        <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $orden->cliente }}</dd>
                    </div>
                    @endif
                    @if($orden->direccion)
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Dirección</dt>
                        <dd class="mt-0.5 text-sm text-zinc-900 dark:text-white">{{ $orden->direccion }}</dd>
                    </div>
                    @endif
                    @if($orden->numero_noticia_criminal)
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">N° Noticia Criminal</dt>
                        <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $orden->numero_noticia_criminal }}</dd>
                    </div>
                    @endif
                </dl>
            </div>

            {{-- Investigador --}}
            @php
                $responsable = $orden->tipo_servicio === \App\Enums\TipoServicio::Vigilancia
                    ? $reporte->personal
                    : $orden->personal;
            @endphp
            <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                    Investigador
                </h3>
                <dl class="space-y-2">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Nombre</dt>
                        <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">
                            {{ $responsable ? "{$responsable->nombres} {$responsable->apellidos}" : '—' }}
                        </dd>
                    </div>
                    @if($responsable?->celular)
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Teléfono</dt>
                        <dd class="mt-0.5 text-sm text-zinc-900 dark:text-white">{{ $responsable->celular }}</dd>
                    </div>
                    @endif
                </dl>
            </div>

            {{-- Ubicación GPS --}}
            @if($reporte->ubicacion?->latitud && $reporte->ubicacion?->longitud)
            <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Ubicación GPS</h3>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                    {{ number_format((float) $reporte->ubicacion->latitud, 6) }},
                    {{ number_format((float) $reporte->ubicacion->longitud, 6) }}
                </p>
                <a
                    href="https://www.google.com/maps?q={{ $reporte->ubicacion->latitud }},{{ $reporte->ubicacion->longitud }}"
                    target="_blank"
                    rel="noopener"
                    class="mt-2 inline-flex items-center gap-1 text-sm text-blue-600 underline hover:text-blue-800 dark:text-blue-400"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    Ver en Google Maps
                </a>
            </div>
            @endif

            {{-- Decisión (solo cuando está en revisión y no es Vigilancia — Vigilancia decide en el índice de reportes) --}}
            @if($orden->estado === \App\Enums\EstadoOrden::EnRevision && $orden->tipo_servicio !== \App\Enums\TipoServicio::Vigilancia)
            <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900"
                 x-data="{ rechazo: @entangle('mostrarFormRechazo') }">
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Decisión</h3>

                <div x-show="!rechazo" class="flex flex-col items-stretch gap-3">
                    <div class="[&>button]:w-full [&>button]:justify-center">
                        {{ $this->completarAction }}
                    </div>
                    <flux:button
                        variant="danger"
                        icon="x-circle"
                        class="w-full justify-center"
                        @click="rechazo = true"
                    >
                        Rechazar Reporte
                    </flux:button>
                </div>

                <div x-show="rechazo" x-cloak>
                    <p class="mb-3 text-sm text-zinc-600 dark:text-zinc-400">
                        Indica el motivo del rechazo. El investigador lo verá al editar el reporte.
                    </p>
                    <textarea
                        wire:model="comentarios"
                        rows="4"
                        placeholder="Describe el motivo del rechazo..."
                        class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm text-zinc-900 placeholder-zinc-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-zinc-600 dark:bg-zinc-800 dark:text-white dark:placeholder-zinc-500"
                    ></textarea>
                    @error('comentarios')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                    <div class="mt-3 flex gap-2">
                        <flux:button
                            variant="danger"
                            class="flex-1"
                            wire:click="rechazar"
                            wire:loading.attr="disabled"
                        >
                            <span wire:loading.remove wire:target="rechazar">Confirmar Rechazo</span>
                            <span wire:loading wire:target="rechazar">Enviando...</span>
                        </flux:button>
                        <flux:button
                            variant="ghost"
                            @click="rechazo = false; $wire.set('comentarios', '')"
                        >
                            Cancelar
                        </flux:button>
                    </div>
                </div>
            </div>
            @endif

        </div>
    </div>

    <x-filament-actions::modals />
</div>
