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
            <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Datos del Punto de Servicio</h3>
                <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-medium text-zinc-400 uppercase tracking-wide">Contrato</dt>
                        <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $orden->contrato }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-400 uppercase tracking-wide">Cliente</dt>
                        <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $orden->cliente }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-400 uppercase tracking-wide">Dirección</dt>
                        <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $orden->direccion }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-400 uppercase tracking-wide">Barrio</dt>
                        <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $orden->barrio }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-400 uppercase tracking-wide">N° Medidor</dt>
                        <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $orden->medidor }}</dd>
                    </div>
                    @if($orden->tipo_medidor)
                    <div>
                        <dt class="text-xs font-medium text-zinc-400 uppercase tracking-wide">Tipo de Medidor</dt>
                        <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $orden->tipo_medidor }}</dd>
                    </div>
                    @endif
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-medium text-zinc-400 uppercase tracking-wide">Ciclo</dt>
                        <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $orden->ciclo }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Fotos --}}
            @if(! empty($fotoUrls))
            <div
                x-data="{ open: false, src: '' }"
                @keydown.escape.window="open = false"
                class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900"
            >
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Fotos ({{ count($fotoUrls) }})</h3>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    @foreach($fotoUrls as $url)
                        <button
                            type="button"
                            @click="src = '{{ $url }}'; open = true"
                            class="block overflow-hidden rounded-lg border border-zinc-100 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-zinc-700"
                        >
                            <img src="{{ $url }}" alt="Foto de orden" class="h-44 w-full object-cover transition hover:opacity-80">
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

            {{-- Descripción --}}
            @if($orden->titulo || $orden->descripcion)
            <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Descripción</h3>
                @if($orden->titulo)
                    <p class="mb-2 font-semibold text-zinc-900 dark:text-white">{{ $orden->titulo }}</p>
                @endif
                @if($orden->descripcion)
                    <p class="text-sm text-zinc-600 dark:text-zinc-300 whitespace-pre-line">{{ $orden->descripcion }}</p>
                @endif
            </div>
            @endif

        </div>

        {{-- Columna lateral --}}
        <div class="space-y-6">

            {{-- Estado y prioridad --}}
            <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Gestión</h3>
                <dl class="space-y-3">
                    <div>
                        <dt class="text-xs font-medium text-zinc-400 uppercase tracking-wide">Prioridad</dt>
                        <dd class="mt-1">
                            <flux:badge :color="match($orden->prioridad->value) {
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
                            <flux:badge :color="match($orden->estado->value) {
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
                        <dt class="text-xs font-medium text-zinc-400 uppercase tracking-wide">Investigador Asignado</dt>
                        <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">
                            {{ $orden->personal ? "{$orden->personal->nombres} {$orden->personal->apellidos}" : '—' }}
                        </dd>
                    </div>
                </dl>
            </div>

            {{-- Fechas --}}
            <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Fechas</h3>
                <dl class="space-y-3">
                    <div>
                        <dt class="text-xs font-medium text-zinc-400 uppercase tracking-wide">Asignación</dt>
                        <dd class="mt-0.5 text-sm text-zinc-900 dark:text-white">
                            {{ $orden->fecha_asignacion?->format('d/m/Y H:i') ?? '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-400 uppercase tracking-wide">Fecha Límite</dt>
                        <dd class="mt-0.5 text-sm font-semibold {{ $orden->fecha_limite && $orden->fecha_limite->isPast() && $orden->estado->value !== 'completada' ? 'text-red-600' : 'text-zinc-900 dark:text-white' }}">
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
