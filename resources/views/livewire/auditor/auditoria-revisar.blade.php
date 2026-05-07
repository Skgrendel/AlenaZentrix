<div class="space-y-6">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Revisar Auditoría</flux:heading>
            <flux:text class="mt-1 text-sm">
                Orden: <span class="font-semibold">{{ $orden?->numero_orden }}</span> — {{ $orden?->cliente }}
                @if($auditoria->estado === \App\Enums\EstadoAuditoria::PendienteAuditoria)
                    <flux:badge color="warning" class="ml-2">Pendiente por auditar</flux:badge>
                @elseif($auditoria->estado === \App\Enums\EstadoAuditoria::Confirmado)
                    <flux:badge color="green" class="ml-2">Confirmado</flux:badge>
                @else
                    <flux:badge color="red" class="ml-2">Negativo</flux:badge>
                @endif
            </flux:text>
        </div>
        <flux:button href="{{ route('auditor.auditorias') }}" icon="arrow-left" variant="ghost" wire:navigate>
            Volver
        </flux:button>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        {{-- Columna principal: reporte del técnico --}}
        <div class="space-y-6 lg:col-span-2">

            {{-- Comercio visitado --}}
            <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Comercio Visitado</h3>
                <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Nombre del Comercio</dt>
                        <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $reporte?->comercio?->nombre_comercio ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Tipo de Comercio</dt>
                        <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $reporte?->comercio?->tipoComercio?->nombre ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Contrato</dt>
                        <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $orden?->contrato }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Dirección</dt>
                        <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $orden?->direccion }}, {{ $orden?->barrio }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Datos de la visita --}}
            <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Datos de la Visita</h3>
                <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Lectura del Medidor</dt>
                        <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $reporte?->lectura ?? '—' }}</dd>
                    </div>
                    @if($reporte?->numero_medidor)
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Número de Medidor</dt>
                        <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $reporte->numero_medidor }}</dd>
                    </div>
                    @endif
                    @if($reporte?->cau)
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">CAU</dt>
                        <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $reporte->cau }}</dd>
                    </div>
                    @endif
                    @if(!empty($reporte?->anomalia))
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
            @if($reporte?->tipoImposibilidad || $reporte?->tipoRegulador || $reporte?->marcaMedidor || $reporte?->marcaRegulador)
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

            {{-- Evidencias del técnico --}}
            @if(!empty($imagenUrls))
            <div
                x-data="{ open: false, src: '' }"
                @keydown.escape.window="open = false"
                class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900"
            >
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Evidencias del Técnico ({{ count($imagenUrls) }} fotos)</h3>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    @foreach($imagenUrls as $url)
                        <button type="button" @click="src = '{{ $url }}'; open = true"
                            class="overflow-hidden rounded-lg border border-zinc-100 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-zinc-700">
                            <img src="{{ $url }}" alt="Evidencia" class="h-40 w-full object-cover transition hover:opacity-80">
                        </button>
                    @endforeach
                </div>
                {{-- Lightbox --}}
                <div x-show="open" x-transition.opacity @click.self="open = false"
                    class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4" style="display:none">
                    <div class="relative max-h-full max-w-4xl">
                        <button type="button" @click="open = false"
                            class="absolute -right-3 -top-3 flex h-8 w-8 items-center justify-center rounded-full bg-white text-zinc-800 shadow-lg hover:bg-zinc-100">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                        <img :src="src" alt="Foto ampliada" class="max-h-[85vh] max-w-full rounded-lg object-contain shadow-2xl">
                    </div>
                </div>
            </div>
            @endif

            @if($reporte?->observaciones)
            <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Observaciones del Técnico</h3>
                <p class="whitespace-pre-line text-sm text-zinc-700 dark:text-zinc-300">{{ $reporte->observaciones }}</p>
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
                            {{ $orden?->personal ? "{$orden->personal->nombres} {$orden->personal->apellidos}" : '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Fecha Completado</dt>
                        <dd class="mt-0.5 text-sm text-zinc-900 dark:text-white">
                            {{ $orden?->fecha_completado?->format('d/m/Y H:i') ?? '—' }}
                        </dd>
                    </div>
                </dl>
            </div>

            {{-- Ubicación GPS --}}
            @if($reporte?->ubicacion?->latitud && $reporte?->ubicacion?->longitud)
            <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Ubicación GPS</h3>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                    {{ number_format((float) $reporte->ubicacion->latitud, 6) }},
                    {{ number_format((float) $reporte->ubicacion->longitud, 6) }}
                </p>
                <a href="https://www.google.com/maps?q={{ $reporte->ubicacion->latitud }},{{ $reporte->ubicacion->longitud }}"
                    target="_blank" rel="noopener"
                    class="mt-2 inline-flex items-center gap-1 text-sm text-blue-600 underline hover:text-blue-800 dark:text-blue-400">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    Ver en Google Maps
                </a>
            </div>
            @endif

            {{-- Formulario o resultado de auditoría --}}
            @if($auditoria->estado === \App\Enums\EstadoAuditoria::PendienteAuditoria)
            <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Registrar Auditoría</h3>
                <form wire:submit="guardar" class="space-y-4">
                    {{ $this->form }}
                    <flux:button type="submit" variant="primary" icon="check-circle" class="w-full justify-center"
                        wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="guardar">Guardar Auditoría</span>
                        <span wire:loading wire:target="guardar">Guardando...</span>
                    </flux:button>
                </form>
            </div>
            @else
            <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Resultado de la Auditoría</h3>
                <dl class="space-y-4">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Resultado</dt>
                        <dd class="mt-1">
                            @if($auditoria->estado === \App\Enums\EstadoAuditoria::Confirmado)
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
                    @if($auditoria->auditadoPor)
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Auditado por</dt>
                        <dd class="mt-0.5 text-sm text-zinc-900 dark:text-white">
                            {{ $auditoria->auditadoPor->name }}
                        </dd>
                    </div>
                    @endif
                    @if($auditoria->observaciones)
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Observaciones</dt>
                        <dd class="mt-0.5 whitespace-pre-line text-sm text-zinc-700 dark:text-zinc-300">{{ $auditoria->observaciones }}</dd>
                    </div>
                    @endif
                    @if(!empty($auditoria->evidencias))
                    <div>
                        <dt class="mb-2 text-xs font-medium uppercase tracking-wide text-zinc-400">Evidencias ({{ count($auditoria->evidencias) }})</dt>
                        <dd class="space-y-2">
                            @foreach($auditoria->evidencias as $evidencia)
                                <a href="{{ route('operaciones.foto', ['path' => $evidencia]) }}"
                                    target="_blank"
                                    class="flex items-center gap-2 rounded-lg border border-zinc-200 px-3 py-2 text-sm text-blue-600 hover:bg-zinc-50 dark:border-zinc-700 dark:text-blue-400 dark:hover:bg-zinc-800">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
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

    <x-filament-actions::modals />
</div>
