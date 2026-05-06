<div class="space-y-6">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Revisar Reporte</flux:heading>
            <flux:text class="mt-1 text-sm">
                Orden: <span class="font-semibold">{{ $orden->numero_orden }}</span> — {{ $orden->cliente }}
                <flux:badge color="purple" class="ml-2">En Revisión</flux:badge>
            </flux:text>
        </div>
        <flux:button href="{{ route('operaciones.ordenes-trabajo') }}" icon="arrow-left" variant="ghost" wire:navigate>
            Volver
        </flux:button>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        {{-- Columna principal --}}
        <div class="space-y-6 lg:col-span-2">

            {{-- Datos del Comercio --}}
            <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Comercio Visitado</h3>
                <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Nombre del Comercio</dt>
                        <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">
                            {{ $reporte->comercio?->nombre_comercio ?? '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Tipo de Comercio</dt>
                        <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">
                            {{ $reporte->comercio?->tipoComercio?->nombre ?? '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Contrato</dt>
                        <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $orden->contrato }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Dirección</dt>
                        <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $orden->direccion }}, {{ $orden->barrio }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Datos del Medidor --}}
            <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Datos de la Visita</h3>
                <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Lectura del Medidor</dt>
                        <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $reporte->lectura }}</dd>
                    </div>
                    @if($reporte->numero_medidor)
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Número de Medidor</dt>
                        <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $reporte->numero_medidor }}</dd>
                    </div>
                    @endif
                    @if($reporte->cau)
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">CAU</dt>
                        <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $reporte->cau }}</dd>
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

            {{-- Clasificación --}}
            @if($reporte->tipoImposibilidad || $reporte->tipoRegulador || $reporte->marcaMedidor || $reporte->marcaRegulador)
            <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Clasificación</h3>
                <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
                    @if($reporte->tipoImposibilidad)
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Tipo de Imposibilidad</dt>
                        <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $reporte->tipoImposibilidad->nombre }}</dd>
                    </div>
                    @endif
                    @if($reporte->tipoRegulador)
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Tipo de Regulador</dt>
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
                        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Marca del Regulador</dt>
                        <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $reporte->marcaRegulador->nombre }}</dd>
                    </div>
                    @endif
                </dl>
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

            @if($reporte->observaciones)
            <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Observaciones del Técnico</h3>
                <p class="text-sm text-zinc-700 dark:text-zinc-300 whitespace-pre-line">{{ $reporte->observaciones }}</p>
            </div>
            @endif

        </div>

        {{-- Columna lateral --}}
        <div class="space-y-6">

            {{-- Técnico --}}
            <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Técnico</h3>
                <dl class="space-y-2">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Nombre</dt>
                        <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">
                            {{ $orden->personal ? "{$orden->personal->nombres} {$orden->personal->apellidos}" : '—' }}
                        </dd>
                    </div>
                    @if($orden->personal?->celular)
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Teléfono</dt>
                        <dd class="mt-0.5 text-sm text-zinc-900 dark:text-white">{{ $orden->personal->celular }}</dd>
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

            {{-- Decisión --}}
            <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900"
                 x-data="{ rechazo: @entangle('mostrarFormRechazo') }">
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Decisión</h3>

                {{-- Botones de acción --}}
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

                {{-- Formulario de rechazo --}}
                <div x-show="rechazo" x-cloak>
                    <p class="mb-3 text-sm text-zinc-600 dark:text-zinc-400">
                        Indica el motivo del rechazo. El técnico lo verá al editar el reporte.
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

        </div>
    </div>

    <x-filament-actions::modals />
</div>
