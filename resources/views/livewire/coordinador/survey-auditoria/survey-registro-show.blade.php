@php
    $dato = fn ($valor) => filled($valor) ? $valor : '—';
    $tarjeta = 'rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900';

    $todas = $grupos->flatMap(fn ($grupo) => $grupo)->values();
    $posicion = $todas->pluck('id')->flip();
    $paraElVisor = $todas->map(fn ($f) => [
        'src' => route('operaciones.survey-auditoria.adjuntos.show', $f),
        'nombre' => $f->nombre,
        'tipo' => $this->etiquetaFoto($f->campo ?? 'otros'),
    ])->values();
@endphp

<div class="space-y-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <flux:heading size="xl">Registro OT {{ $registro->orden_trabajo }}</flux:heading>
            <flux:text class="mt-1 text-sm">Contrato {{ $dato($registro->contrato) }} · Técnico {{ $dato($registro->tecnico) }}</flux:text>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <flux:button :href="route('operaciones.survey-auditoria.index')" icon="arrow-left" size="sm" wire:navigate>Volver a la lista</flux:button>
        </div>
    </div>

    <div class="grid items-stretch gap-5 lg:grid-cols-3">
        {{-- Datos reportados por el técnico --}}
        <div class="{{ $tarjeta }} lg:col-span-2">
            <h3 class="mb-3 text-base font-semibold text-zinc-900 dark:text-white">Datos reportados por el técnico</h3>
            <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm md:grid-cols-3">
                @foreach ([
                    'Fecha de registro' => $registro->fecha_registro?->format('d/m/Y h:i a'),
                    'Enviado' => $registro->creado_en_origen?->format('d/m/Y h:i a'),
                    'Última edición' => $registro->editado_en_origen?->format('d/m/Y h:i a'),
                    'Categoría' => $registro->categoria ? ucfirst($registro->categoria) : null,
                    'Actividad' => $registro->actividad ? ucfirst($registro->actividad) : null,
                    '¿Suspensión ejecutada?' => $registro->suspension_ejecutada === null ? null : ($registro->suspension_ejecutada ? 'Sí' : 'No'),
                    'Método de suspensión' => $metodo,
                    'Serie del medidor' => $registro->serie_medidor,
                    'Lectura del medidor' => $registro->lectura_medidor,
                    'Serie de la sonda' => $registro->serie_sonda,
                    'Serie del Caps' => $registro->serie_caps,
                    'Motivo de no ejecución' => $motivo,
                    'ObjectID' => $registro->object_id,
                ] as $etiqueta => $valor)
                    <div>
                        <dt class="text-xs font-medium uppercase text-zinc-400">{{ $etiqueta }}</dt>
                        <dd class="mt-0.5 font-medium text-zinc-900 dark:text-white">{{ $dato($valor) }}</dd>
                    </div>
                @endforeach
                <div>
                    <dt class="text-xs font-medium uppercase text-zinc-400">Ubicación de la visita</dt>
                    <dd class="mt-0.5 font-medium text-zinc-900 dark:text-white">
                        @if ($registro->latitud !== null && $registro->longitud !== null)
                            <a href="https://www.google.com/maps/place/{{ $registro->latitud }},{{ $registro->longitud }}" target="_blank" rel="noopener noreferrer" class="text-orange-600 hover:underline">Ver en el mapa</a>
                            <span class="block text-xs font-normal text-zinc-500">{{ round((float) $registro->latitud, 5) }}, {{ round((float) $registro->longitud, 5) }}</span>
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div class="col-span-full">
                    <dt class="text-xs font-medium uppercase text-zinc-400">Justificación</dt>
                    <dd class="mt-0.5 text-zinc-900 dark:text-white">{{ $dato($registro->justificacion) }}</dd>
                </div>
            </dl>
        </div>

        {{-- Hallazgos y lista de verificación de fotos --}}
        <div class="flex flex-col gap-5">
            <div class="{{ $tarjeta }} flex-1">
                <h3 class="mb-2 text-base font-semibold text-zinc-900 dark:text-white">Hallazgos de la auditoría</h3>
                <div class="mb-3 flex flex-wrap gap-2">
                    <span class="inline-flex rounded-full bg-red-100 px-3 py-1 text-xs font-semibold text-red-800 dark:bg-red-900/30 dark:text-red-300">{{ $registro->total_errores }} {{ $registro->total_errores === 1 ? 'error' : 'errores' }}</span>
                    <span class="inline-flex rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">{{ $registro->total_alertas }} {{ $registro->total_alertas === 1 ? 'alerta' : 'alertas' }}</span>
                    <span class="inline-flex rounded-full bg-zinc-100 px-3 py-1 text-xs font-semibold text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">{{ $registro->estado->label() }}</span>
                </div>
                @if ($registro->auditado_en === null)
                    <flux:text class="text-sm">Este registro todavía no se ha auditado.</flux:text>
                @elseif ($hallazgos->isEmpty())
                    <flux:text class="text-sm">Sin hallazgos: el registro cumple todas las reglas.</flux:text>
                @else
                    <ul class="space-y-2 text-sm">
                        @foreach ($hallazgos as $hallazgo)
                            <li class="flex gap-2">
                                <span class="{{ $hallazgo['severidad'] === 'error' ? 'text-red-600' : 'text-amber-600' }} shrink-0 font-semibold">{{ $hallazgo['severidad'] === 'error' ? 'Error' : 'Alerta' }}</span>
                                <span class="text-zinc-700 dark:text-zinc-300">{{ $hallazgo['mensaje'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="{{ $tarjeta }} flex-1">
                <h3 class="mb-3 text-base font-semibold text-zinc-900 dark:text-white">Fotos requeridas</h3>
                <ul class="space-y-2 text-sm">
                    @foreach ($listaFotos as $item)
                        @php
                            $estado = ! $item['aplica'] ? 'na' : ($item['completa'] ? 'ok' : 'falta');
                        @endphp
                        <li class="flex items-center justify-between rounded-lg border px-3 py-2
                            {{ $estado === 'ok' ? 'border-green-200 bg-green-50 text-green-800 dark:border-green-900 dark:bg-green-900/20 dark:text-green-300' : '' }}
                            {{ $estado === 'falta' ? 'border-red-200 bg-red-50 text-red-800 dark:border-red-900 dark:bg-red-900/20 dark:text-red-300' : '' }}
                            {{ $estado === 'na' ? 'border-zinc-200 bg-zinc-50 text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400' : '' }}">
                            <span>{{ $item['etiqueta'] }}</span>
                            <span class="font-semibold">
                                @if ($estado === 'na')
                                    No aplica
                                @else
                                    {{ $estado === 'ok' ? '✔' : '✖' }} {{ $item['subidas'] }} de {{ $item['requeridas'] }}
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>

    {{-- Fotos --}}
    <div
        class="{{ $tarjeta }}"
        x-data="{
            fotos: @js($paraElVisor),
            abierta: null,
            abrir(i) { this.abierta = i },
            cerrar() { this.abierta = null },
            siguiente() { if (this.abierta !== null) this.abierta = (this.abierta + 1) % this.fotos.length },
            anterior() { if (this.abierta !== null) this.abierta = (this.abierta - 1 + this.fotos.length) % this.fotos.length },
        }"
        x-effect="document.body.classList.toggle('overflow-hidden', abierta !== null)"
        @keydown.escape.window="cerrar()"
        @keydown.arrow-right.window="siguiente()"
        @keydown.arrow-left.window="anterior()"
    >
        <h3 class="mb-3 text-base font-semibold text-zinc-900 dark:text-white">Fotos del técnico ({{ $todas->count() }})</h3>

        @if ($todas->isEmpty())
            <flux:text class="text-sm">Este registro no tiene fotos copiadas. Ejecuta la sincronización para traerlas.</flux:text>
        @else
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
                @foreach ($todas as $foto)
                    <figure class="overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700">
                        <button type="button" class="relative block w-full cursor-zoom-in" @click="abrir({{ $posicion[$foto->id] }})" aria-label="Ampliar la foto {{ $foto->nombre }}">
                            <img src="{{ route('operaciones.survey-auditoria.adjuntos.show', $foto) }}" alt="{{ $foto->nombre }}" loading="lazy" class="aspect-4/3 w-full bg-zinc-100 object-cover dark:bg-zinc-800">
                            <span class="absolute left-1.5 top-1.5 rounded bg-black/60 px-1.5 py-0.5 text-[11px] font-medium text-white">{{ $this->etiquetaFoto($foto->campo ?? 'otros') }}</span>
                        </button>
                        <figcaption class="space-y-1 p-2 text-xs text-zinc-500">
                            <div class="truncate" title="{{ $foto->nombre }}">{{ $foto->nombre }}</div>
                            @if ($foto->repetida_aqui)
                                <span class="inline-flex rounded-full bg-red-100 px-2 py-0.5 font-semibold text-red-800 dark:bg-red-900/30 dark:text-red-300">Repetida en este registro</span>
                            @endif
                            @if ($foto->usada_en_otra_visita)
                                <span class="inline-flex rounded-full bg-red-100 px-2 py-0.5 font-semibold text-red-800 dark:bg-red-900/30 dark:text-red-300">Usada en otra visita</span>
                            @endif
                        </figcaption>
                    </figure>
                @endforeach
            </div>
        @endif

        {{-- Visor emergente de fotos --}}
        <div
            x-show="abierta !== null"
            x-transition.opacity
            style="display: none"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/85 p-4"
            role="dialog"
            aria-modal="true"
            aria-label="Visor de fotos"
            @click.self="cerrar()"
        >
            <button type="button" class="absolute right-4 top-4 rounded-full bg-white/10 px-3 py-1.5 text-sm font-medium text-white hover:bg-white/20" @click="cerrar()" aria-label="Cerrar">✕ Cerrar</button>

            <template x-if="fotos.length > 1">
                <button type="button" class="absolute left-4 top-1/2 -translate-y-1/2 rounded-full bg-white/10 px-4 py-3 text-2xl text-white hover:bg-white/20" @click="anterior()" aria-label="Foto anterior">‹</button>
            </template>

            <figure class="flex max-h-full max-w-full flex-col items-center gap-3" x-show="abierta !== null">
                <img :src="abierta !== null ? fotos[abierta].src : ''" :alt="abierta !== null ? fotos[abierta].nombre : ''" class="max-h-[80vh] max-w-full rounded-lg object-contain shadow-2xl">
                <figcaption class="text-center text-sm text-white/90" x-show="abierta !== null">
                    <span x-text="abierta !== null ? fotos[abierta].tipo : ''" class="font-semibold"></span>
                    ·
                    <span x-text="abierta !== null ? fotos[abierta].nombre : ''"></span>
                    ·
                    <span x-text="abierta !== null ? (abierta + 1) + ' de ' + fotos.length : ''"></span>
                </figcaption>
            </figure>

            <template x-if="fotos.length > 1">
                <button type="button" class="absolute right-4 top-1/2 -translate-y-1/2 rounded-full bg-white/10 px-4 py-3 text-2xl text-white hover:bg-white/20" @click="siguiente()" aria-label="Foto siguiente">›</button>
            </template>
        </div>
    </div>

    {{-- Sugerencia de la IA sobre las fotos --}}
    <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
        <div class="mb-3 flex flex-wrap items-start justify-between gap-2">
            <div>
                <h3 class="text-base font-semibold text-zinc-900 dark:text-white">Revisión de fotos con IA</h3>
                <flux:text class="text-xs">Es solo una sugerencia: no cambia el resultado de la auditoría y la decisión es de una persona.</flux:text>
            </div>
            @if ($puedeRevisarConIA && $registro->adjuntos->isNotEmpty())
                <flux:button size="sm" icon="sparkles" wire:click="revisarConIA" wire:loading.attr="disabled" wire:target="revisarConIA">
                    <span wire:loading.remove wire:target="revisarConIA">{{ $revisionIA ? 'Volver a revisar' : 'Revisar con IA' }}</span>
                    <span wire:loading wire:target="revisarConIA">Revisando…</span>
                </flux:button>
            @endif
        </div>

        @if ($revisionIA)
            <flux:text class="mb-3 text-xs">
                Revisado el {{ $revisionIA->created_at->timezone(config('survey_auditoria.zona_horaria_excel'))->format('d/m/Y h:i a') }}
                @if ($revisionIA->usuario) por {{ $revisionIA->usuario->fullName() }} @endif
            </flux:text>
            @include('livewire.partials.veredicto-fotos-ia', ['revision' => [
                'veredicto' => $revisionIA->veredicto,
                'modelo' => $revisionIA->modelo,
                'fotos' => $revisionIA->fotos,
                'tokens_entrada' => $revisionIA->tokens_entrada,
                'tokens_salida' => $revisionIA->tokens_salida,
                'costo_usd' => $revisionIA->costo_usd,
            ]])
        @else
            <flux:text class="text-sm">Todavía no se ha revisado con IA.</flux:text>
        @endif
    </div>

    {{-- Resultado de la visita del auditor --}}
    @php($reporte = $orden?->reportes->sortByDesc('created_at')->first())
    @if ($reporte && ! empty($reporte->comparacion['filas'] ?? []))
        @include('livewire.coordinador.partials.comparacion-suspension', ['comparacion' => $reporte->comparacion, 'resultado' => $reporte->resultado_comparacion])
    @endif
</div>
