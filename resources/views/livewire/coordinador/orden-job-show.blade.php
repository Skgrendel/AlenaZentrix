<div class="space-y-6">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Orden: {{ $orden->numero_orden }}</flux:heading>
            <flux:text class="mt-1 text-sm">Creada el {{ $orden->created_at->format('d/m/Y') }}</flux:text>
        </div>
        <flux:button href="{{ route('operaciones.ordenes-trabajo') }}" icon="arrow-left" variant="ghost" wire:navigate>
            Volver
        </flux:button>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        {{-- Columna principal --}}
        <div class="space-y-6 lg:col-span-2">

            {{-- Datos del punto de servicio --}}
            @if (
                $orden->contrato ||
                    $orden->cliente ||
                    $orden->direccion ||
                    $orden->barrio ||
                    $orden->medidor ||
                    $orden->tipo_medidor ||
                    $orden->ciclo)
                <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                        Datos del Punto de Servicio</h3>
                    <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">

                        @if ($orden->contrato)
                            <div>
                                <dt class="text-xs font-medium text-zinc-400 uppercase tracking-wide">Contrato</dt>
                                <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">
                                    {{ $orden->contrato }}</dd>
                            </div>
                        @endif

                        @if ($orden->cliente)
                            <div>
                                <dt class="text-xs font-medium text-zinc-400 uppercase tracking-wide">Cliente</dt>
                                <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">
                                    {{ $orden->cliente }}
                                </dd>
                            </div>
                        @endif

                        @if ($orden->direccion)
                            <div>
                                <dt class="text-xs font-medium text-zinc-400 uppercase tracking-wide">Dirección</dt>
                                <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">
                                    {{ $orden->direccion }}
                                </dd>
                            </div>
                        @endif

                        @if ($orden->barrio)
                            <div>
                                <dt class="text-xs font-medium text-zinc-400 uppercase tracking-wide">Barrio</dt>
                                <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">
                                    {{ $orden->barrio }}
                                </dd>
                            </div>
                        @endif

                        @if ($orden->medidor)
                            <div>
                                <dt class="text-xs font-medium text-zinc-400 uppercase tracking-wide">N° Medidor</dt>
                                <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">
                                    {{ $orden->medidor }}
                                </dd>
                            </div>
                        @endif

                        @if ($orden->tipo_medidor)
                            <div>
                                <dt class="text-xs font-medium text-zinc-400 uppercase tracking-wide">Tipo de Medidor
                                </dt>
                                <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">
                                    {{ $orden->tipo_medidor }}</dd>
                            </div>
                        @endif

                        @if ($orden->ciclo)
                            <div class="sm:col-span-2">
                                <dt class="text-xs font-medium text-zinc-400 uppercase tracking-wide">Ciclo</dt>
                                <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $orden->ciclo }}
                                </dd>
                            </div>
                        @endif

                    </dl>
                </div>
            @endif

            {{-- Datos del tecnico --}}
            @if ($orden->tecnico)
                <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                    </h3>
                    Datos del Técnico</h3>
                    <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2"></dl>
                    <div>
                        <dt class="text-xs font-medium text-zinc-400 uppercase tracking-wide">Nombre del Técnico</dt>
                        <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">
                            {{ $orden->tecnico }}
                        </dd>
                    </div>
                    </dl>
                </div>
            @endif

            {{-- comentarios usuario --}}
            @if ($orden->comentario_usuario)
                <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                    </h3>
                    Comentarios del Usuario</h3>
                    <p class="mb-2 font-semibold text-zinc-900 dark:text-white">{{ $orden->comentario_usuario }}</p>
                </div>
            @endif

            {{-- comentarios tecnico --}}
            @if ($orden->comentario_tecnico)
                <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                    </h3>
                    Comentarios del Técnico en la Solicitud</h3>
                    <p class="mb-2 font-semibold text-zinc-900 dark:text-white">{{ $orden->comentario_tecnico }}</p>
                </div>
            @endif

            {{-- Descripción --}}
            @if ($orden->antecedente)
                <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                        Antecedentes de la Orden</h3>
                    <p class="mb-2 font-semibold text-zinc-900 dark:text-white">{{ $orden->antecedente }}</p>
                </div>
            @endif

            {{-- Objetivo --}}
            @if ($orden->objetivo)
                <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                        Objetivo de la Orden</h3>
                    <p class="mb-2 font-semibold text-zinc-900 dark:text-white">{{ $orden->objetivo }}</p>
                </div>
            @endif

            {{-- Actividades --}}
            @if (!empty($orden->actividades))
                <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                        Actividades a Realizar</h3>
                    <ul class="list-disc space-y-1 pl-5">
                        @foreach ($orden->actividades as $actividad)
                            <li class="mb-2 font-semibold text-zinc-900 dark:text-white">
                                {{ $actividad['descripcion'] ?? '' }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Documentos --}}
            @if (!empty($documentoUrls))
                <div x-data="{ open: false, src: '' }" @keydown.escape.window="open = false"
                    class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                        Documentos ({{ count($documentoUrls) }})</h3>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        @foreach ($documentoUrls as $url)
                            @php
                                $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
                                $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg']);
                            @endphp
                            @if ($isImage)
                                <button type="button" @click="src = '{{ $url }}'; open = true"
                                    class="block overflow-hidden rounded-lg border border-zinc-100 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-zinc-700">
                                    <img src="{{ $url }}" alt="Documento de orden"
                                        class="h-44 w-full object-cover transition hover:opacity-80">
                                </button>
                            @else
                                <a href="{{ $url }}" download
                                    class="flex flex-col items-center justify-center gap-2 rounded-lg border border-zinc-100 bg-zinc-50 p-4 transition hover:bg-zinc-100 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-zinc-700 dark:bg-zinc-800 dark:hover:bg-zinc-700">
                                    <svg xmlns="http://www.w3.org/2000/svg"
                                        class="h-8 w-8 text-zinc-600 dark:text-zinc-300" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <span class="text-xs font-medium text-zinc-600 dark:text-zinc-300">
                                        {{ strtoupper($extension) }}
                                    </span>
                                </a>
                            @endif
                        @endforeach
                    </div>

                    {{-- Lightbox --}}
                    <div x-show="open" x-transition.opacity @click.self="open = false"
                        class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4"
                        style="display: none">
                        <div class="relative max-h-full max-w-4xl">
                            <button type="button" @click="open = false"
                                class="absolute -right-3 -top-3 flex h-8 w-8 items-center justify-center rounded-full bg-white text-zinc-800 shadow-lg hover:bg-zinc-100">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                            <img :src="src" alt="Foto ampliada"
                                class="max-h-[85vh] max-w-full rounded-lg object-contain shadow-2xl">
                        </div>
                    </div>
                </div>
            @endif

        </div>

        {{-- Columna lateral --}}
        <div class="space-y-6">

            {{-- Estado y prioridad --}}
            <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Gestión
                </h3>
                <dl class="space-y-3">
                    <div>
                        <dt class="text-xs font-medium text-zinc-400 uppercase tracking-wide">Tipo de Servicio</dt>
                        <dd class="mt-1">
                            <flux:badge
                                :color="match($orden->tipo_servicio->value) {
                                                                                                                                    'fraudes'           => 'zinc',
                                                                                                                                    'investigacion'     => 'blue',
                                                                                                                                    'gestion_legal'     => 'info',
                                                                                                                                    'vigilancia'        => 'green',
                                                                                                                                    'Gestión de Riesgo' => 'purple',
                                                                                                                                    default             => 'zinc',
                                                                                                                                }">
                                {{ $orden->tipo_servicio->label() }}
                            </flux:badge>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-400 uppercase tracking-wide">Prioridad</dt>
                        <dd class="mt-1">
                            <flux:badge
                                :color="match($orden->prioridad->value) {
                                                                                                                                                                                                                                                                'baja'    => 'zinc',
                                                                                                                                                                                                                                                                'media'   => 'blue',
                                                                                                                                                                                                                                                                'alta'    => 'yellow',
                                                                                                                                                                                                                                                                'urgente' => 'red',
                                                                                                                                                                                                                                                                default   => 'zinc',
                                                                                                                                                                                                                                                            }">
                                {{ $orden->prioridad->label() }}
                            </flux:badge>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-400 uppercase tracking-wide">Estado</dt>
                        <dd class="mt-1">
                            <flux:badge
                                :color="match($orden->estado->value) {
                                                                                                                                                                                                                                                                'pendiente'   => 'zinc',
                                                                                                                                                                                                                                                                'asignada'    => 'blue',
                                                                                                                                                                                                                                                                'en_progreso' => 'yellow',
                                                                                                                                                                                                                                                                'en_revision' => 'purple',
                                                                                                                                                                                                                                                                'completada'  => 'green',
                                                                                                                                                                                                                                                                'rechazada'   => 'red',
                                                                                                                                                                                                                                                                'cancelada'   => 'zinc',
                                                                                                                                                                                                                                                                default       => 'zinc',
                                                                                                                                                                                                                                                            }">
                                {{ $orden->estado->label() }}
                            </flux:badge>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-400 uppercase tracking-wide">Servicio</dt>
                        <dd class="mt-1">
                            <flux:badge :color="$orden->estado_servicio ? 'green' : 'zinc'">
                                {{ $orden->estado_servicio ? 'Activo' : 'Inactivo' }}
                            </flux:badge>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-400 uppercase tracking-wide">Investigador Asignado
                        </dt>
                        <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">
                            {{ $orden->personal ? "{$orden->personal->nombres} {$orden->personal->apellidos}" : '—' }}
                        </dd>
                    </div>
                </dl>
            </div>

            {{-- Fechas --}}
            <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Fechas
                </h3>
                <dl class="space-y-3">
                    <div>
                        <dt class="text-xs font-medium text-zinc-400 uppercase tracking-wide">Asignación</dt>
                        <dd class="mt-0.5 text-sm text-zinc-900 dark:text-white">
                            {{ $orden->fecha_asignacion?->format('d/m/Y H:i') ?? '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-400 uppercase tracking-wide">Fecha Límite</dt>
                        <dd
                            class="mt-0.5 text-sm font-semibold {{ $orden->fecha_limite && $orden->fecha_limite->isPast() && $orden->estado->value !== 'completada' ? 'text-red-600' : 'text-zinc-900 dark:text-white' }}">
                            {{ $orden->fecha_limite?->format('d/m/Y H:i') ?? '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-400 uppercase tracking-wide">Completado</dt>
                        <dd class="mt-0.5 text-sm text-zinc-900 dark:text-white">
                            {{ $orden->fecha_completado?->format('d/m/Y H:i') ?? '—' }}
                        </dd>
                    </div>
                </dl>
            </div>

        </div>
    </div>
</div>
