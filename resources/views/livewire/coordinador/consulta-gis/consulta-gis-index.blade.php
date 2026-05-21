<div class="max-w-4xl mx-auto py-8 px-4">

    {{-- Encabezado --}}
    <div class="mb-6">
        <flux:heading size="xl">Consulta GIS</flux:heading>
        <flux:subheading>Busca la información de un contrato en el sistema GIS de la red.</flux:subheading>
    </div>

    {{-- Formulario de búsqueda --}}
    <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
        <form wire:submit="buscar">
            {{ $this->form }}
            <div class="mt-4 flex gap-3">
                <flux:button type="submit" variant="primary" icon="magnifying-glass"
                    wire:loading.attr="disabled" wire:target="buscar">
                    <span wire:loading.remove wire:target="buscar">Buscar</span>
                    <span wire:loading wire:target="buscar">Consultando GIS...</span>
                </flux:button>

                @if ($resultado || $error)
                    <flux:button type="button" wire:click="limpiar" variant="ghost" icon="x-mark">
                        Limpiar
                    </flux:button>
                @endif
            </div>
        </form>
    </div>

    {{-- Error --}}
    @if ($error)
        <div class="mt-6 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-950">
            <flux:icon.exclamation-circle class="mt-0.5 h-5 w-5 shrink-0 text-red-500" />
            <p class="text-sm text-red-700 dark:text-red-300">{{ $error }}</p>
        </div>
    @endif

    {{-- Resultado --}}
    @if ($resultado)
        <div class="mt-6 space-y-4">

            {{-- Header del resultado + botón Maps --}}
            <div class="flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">Titular</p>
                    <h2 class="mt-0.5 text-xl font-bold text-gray-900 dark:text-white">
                        {{ $resultado['titular']['nombre'] ?? '—' }}
                    </h2>
                    @if ($resultado['titular']['zona'])
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $resultado['titular']['zona'] }}</p>
                    @endif
                    <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-sm text-gray-500 dark:text-gray-400">
                        <span>Contrato: <span class="font-medium text-gray-700 dark:text-gray-200">{{ $resultado['titular']['contrato'] ?? '—' }}</span></span>
                        <span>NUP: <span class="font-medium text-gray-700 dark:text-gray-200">{{ $resultado['titular']['nup'] ?? '—' }}</span></span>
                        <span>Orden: <span class="font-medium text-gray-700 dark:text-gray-200">{{ $resultado['titular']['orden'] ?? '—' }}</span></span>
                    </div>
                </div>
                <a href="{{ $resultado['coordenadas']['maps_url'] }}" target="_blank" rel="noopener noreferrer">
                    <flux:button variant="primary" icon="map-pin">
                        Ver en Google Maps
                    </flux:button>
                </a>
            </div>

            {{-- Grid de secciones --}}
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">

                {{-- Ubicación --}}
                <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
                    <p class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                        <flux:icon.map-pin class="h-4 w-4" /> Ubicación
                    </p>
                    <dl class="space-y-2">
                        @foreach ([
                            'Dirección' => $resultado['ubicacion']['direccion'],
                            'Barrio' => $resultado['ubicacion']['barrio'],
                            'TAG' => $resultado['ubicacion']['tag'],
                        ] as $label => $valor)
                            @if ($valor)
                                <div class="flex justify-between gap-2 text-sm">
                                    <dt class="shrink-0 text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                                    <dd class="text-right font-medium text-gray-800 dark:text-gray-200">{{ $valor }}</dd>
                                </div>
                            @endif
                        @endforeach
                    </dl>
                </div>

                {{-- Datos del servicio --}}
                <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
                    <p class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                        <flux:icon.bolt class="h-4 w-4" /> Servicio
                    </p>
                    <dl class="space-y-2">
                        @php
                            $estadoColores = [
                                'ACTIVO' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400',
                                'SUSPENDIDO' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
                                'CORTADO' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
                            ];
                            $estadoServicio = strtoupper($resultado['servicio']['estado'] ?? '');
                            $colorEstado = $estadoColores[$estadoServicio] ?? 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300';
                        @endphp
                        @if ($resultado['servicio']['estado'])
                            <div class="flex justify-between gap-2 text-sm">
                                <dt class="shrink-0 text-gray-500 dark:text-gray-400">Estado</dt>
                                <dd>
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $colorEstado }}">
                                        {{ $resultado['servicio']['estado'] }}
                                    </span>
                                </dd>
                            </div>
                        @endif
                        @foreach ([
                            'Estado de Corte' => $resultado['servicio']['estado_corte'],
                            'Descripción' => $resultado['servicio']['descripcion'],
                            'Categoría' => $resultado['servicio']['categoria'],
                            'Estrato' => $resultado['servicio']['estrato'],
                            'Ciclo' => $resultado['servicio']['ciclo'],
                            'Anillado' => $resultado['servicio']['anillado'],
                        ] as $label => $valor)
                            @if ($valor)
                                <div class="flex justify-between gap-2 text-sm">
                                    <dt class="shrink-0 text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                                    <dd class="text-right font-medium text-gray-800 dark:text-gray-200">{{ $valor }}</dd>
                                </div>
                            @endif
                        @endforeach
                    </dl>
                </div>

                {{-- Medidor --}}
                <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
                    <p class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                        <flux:icon.cpu-chip class="h-4 w-4" /> Medidor
                    </p>
                    <dl class="space-y-2">
                        @foreach ([
                            'Serie Actual' => $resultado['medidor']['serie_actual'],
                            'Serie Anterior' => $resultado['medidor']['serie_anterior'],
                            'Fecha Anterior' => $resultado['medidor']['fecha_anterior'],
                        ] as $label => $valor)
                            @if ($valor)
                                <div class="flex justify-between gap-2 text-sm">
                                    <dt class="shrink-0 text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                                    <dd class="text-right font-medium text-gray-800 dark:text-gray-200">{{ $valor }}</dd>
                                </div>
                            @endif
                        @endforeach
                    </dl>
                </div>

                {{-- Consumos --}}
                <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
                    <p class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                        <flux:icon.chart-bar class="h-4 w-4" /> Consumos (últimos 6 meses)
                    </p>
                    @php
                        $consumos = array_values(array_filter([
                            $resultado['consumos']['mes6'],
                            $resultado['consumos']['mes5'],
                            $resultado['consumos']['mes4'],
                            $resultado['consumos']['mes3'],
                            $resultado['consumos']['mes2'],
                            $resultado['consumos']['mes1'],
                        ], fn ($v) => $v !== null));
                        $maxConsumo = count($consumos) ? max(array_map('intval', $consumos)) : 1;
                    @endphp
                    @if (count($consumos))
                        <div class="space-y-2">
                            @foreach ($consumos as $i => $consumo)
                                @php $pct = $maxConsumo > 0 ? intval($consumo) / $maxConsumo * 100 : 0; @endphp
                                <div class="flex items-center gap-3 text-sm">
                                    <span class="w-10 shrink-0 text-right text-gray-500 dark:text-gray-400">
                                        M-{{ count($consumos) - $i }}
                                    </span>
                                    <div class="flex-1 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                                        <div class="h-2 rounded-full bg-primary-500 transition-all"
                                            style="width: {{ $pct }}%"></div>
                                    </div>
                                    <span class="w-14 shrink-0 font-medium text-gray-800 dark:text-gray-200">
                                        {{ number_format(intval($consumo)) }} m³
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-gray-400 dark:text-gray-500">Sin datos de consumo.</p>
                    @endif
                </div>

            </div>

            {{-- Coordenadas --}}
            <div class="rounded-xl border border-gray-200 bg-gray-50 px-5 py-3 dark:border-gray-700 dark:bg-gray-800/50">
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Coordenadas:
                    <span class="ml-1 font-mono text-gray-700 dark:text-gray-200">
                        {{ number_format($resultado['coordenadas']['lat'], 6) }},
                        {{ number_format($resultado['coordenadas']['lng'], 6) }}
                    </span>
                </p>
            </div>

        </div>
    @endif

    <x-filament-actions::modals />
</div>
