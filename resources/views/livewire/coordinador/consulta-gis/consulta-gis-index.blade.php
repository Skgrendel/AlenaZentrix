<div class="mx-auto max-w-4xl px-4 py-8">

    {{-- Page header --}}
    <div class="mb-6">
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-100 dark:bg-primary-900/40">
                <flux:icon.globe-alt class="h-5 w-5 text-primary-600 dark:text-primary-400" />
            </div>
            <div>
                <flux:heading size="xl">Consulta de Ubicacion</flux:heading>
                <flux:subheading>Consulta en tiempo real la ubicación de la red de distribución.</flux:subheading>
            </div>
        </div>
    </div>

    {{-- Search form --}}
    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="border-b border-gray-100 bg-gray-50/60 px-6 py-3 dark:border-gray-800 dark:bg-gray-800/40">
            <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">Búsqueda por contrato</p>
        </div>
        <div class="p-6">
            <form wire:submit="buscar">
                {{ $this->form }}
                <div class="mt-4 flex flex-wrap gap-3">
                    <flux:button type="submit" variant="primary" icon="magnifying-glass"
                        wire:loading.attr="disabled" wire:target="buscar" class="min-w-32">
                        <span wire:loading.remove wire:target="buscar">Buscar</span>
                        <span wire:loading wire:target="buscar">Consultando...</span>
                    </flux:button>
                </div>
            </form>
        </div>
    </div>

    {{-- Error --}}
    @if ($error)
        <div class="mt-5 flex items-start gap-4 rounded-2xl border border-red-200 bg-red-50 p-5 dark:border-red-800/50 dark:bg-red-950/40">
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/50">
                <flux:icon.exclamation-triangle class="h-5 w-5 text-red-600 dark:text-red-400" />
            </div>
            <div>
                <p class="font-semibold text-red-800 dark:text-red-300">No se pudo obtener información</p>
                <p class="mt-0.5 text-sm text-red-600 dark:text-red-400">{{ $error }}</p>
            </div>
        </div>
    @endif

    {{-- Result --}}
    @if ($resultado)
        @php
            $nombre = $resultado['titular']['nombre'] ?? null;
            $iniciales = $nombre
                ? collect(explode(' ', $nombre))->take(2)->map(fn($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('')
                : '?';
            $municipio = $resultado['ubicacion']['municipio'] ?? null;

            $consumosRaw = [
                'M-6' => $resultado['consumos']['mes6'],
                'M-5' => $resultado['consumos']['mes5'],
                'M-4' => $resultado['consumos']['mes4'],
                'M-3' => $resultado['consumos']['mes3'],
                'M-2' => $resultado['consumos']['mes2'],
                'M-1' => $resultado['consumos']['mes1'],
            ];
            $consumosFiltrados = array_filter($consumosRaw, fn($v) => $v !== null && intval($v) > 0);
            $maxConsumo = count($consumosFiltrados) ? max(array_map('intval', $consumosFiltrados)) : 0;
        @endphp

        <div class="mt-5 space-y-4">

            {{-- Titular hero card --}}
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="h-1.5 w-full bg-linear-to-r from-primary-500 to-primary-400"></div>
                <div class="flex flex-col gap-4 p-6 sm:flex-row sm:items-start sm:justify-between">
                    <div class="flex items-start gap-4">
                        <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-primary-100 dark:bg-primary-900/50">
                            <span class="text-xl font-bold text-primary-700 dark:text-primary-300">{{ $iniciales }}</span>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold tracking-tight text-gray-900 dark:text-white">
                                {{ $nombre ?? '—' }}
                            </h2>
                            @if ($municipio)
                                <p class="text-sm font-medium text-primary-600 dark:text-primary-400">
                                    {{ $municipio }}
                                </p>
                            @endif
                            <div class="mt-2.5 flex flex-wrap gap-2">
                                @if ($resultado['titular']['contrato'])
                                    <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                        <flux:icon.hashtag class="h-3 w-3 opacity-60" />
                                        Contrato: {{ $resultado['titular']['contrato'] }}
                                    </span>
                                @endif
                                @if ($resultado['titular']['nup'])
                                    <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                        <flux:icon.identification class="h-3 w-3 opacity-60" />
                                        NUP: {{ $resultado['titular']['nup'] }}
                                    </span>
                                @endif
                                @if ($resultado['titular']['orden'])
                                    <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                        <flux:icon.clipboard-document class="h-3 w-3 opacity-60" />
                                        Orden: {{ $resultado['titular']['orden'] }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                    <a href="{{ $resultado['coordenadas']['maps_url'] }}" target="_blank" rel="noopener noreferrer" class="shrink-0">
                        <flux:button variant="primary" icon="map-pin" class="w-full sm:w-auto">
                            Ver en Google Maps
                        </flux:button>
                    </a>
                </div>
            </div>

            @php
                $ubicacion = $resultado['ubicacion'] ?? [];
                $servicio = $resultado['servicio'] ?? [];
                $medidor = $resultado['medidor'] ?? [];

                $anillado = match (strtoupper((string) ($servicio['anillado'] ?? ''))) {
                    'Y' => 'Sí',
                    'N' => 'No',
                    default => $servicio['anillado'] ?? null,
                };

                $estadoClases = match (strtoupper((string) ($servicio['estado'] ?? ''))) {
                    'ACTIVO' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
                    'SUSPENDIDO' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
                    'CORTADO' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
                    default => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
                };

                $cardClasses = 'flex h-full flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900';
                $labelClasses = 'text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500';
                $valueClasses = 'mt-0.5 text-sm font-semibold text-gray-900 dark:text-gray-100 wrap-break-word';
            @endphp

            {{-- Fila 1: Ubicación y Servicio --}}
            <div class="grid gap-4 md:grid-cols-2">

                {{-- Ubicación --}}
                <div class="{{ $cardClasses }}">
                    <div class="flex items-center gap-2.5 border-b border-gray-100 px-5 py-3.5 dark:border-gray-800">
                        <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-sky-100 dark:bg-sky-900/40">
                            <flux:icon.map-pin class="h-4 w-4 text-sky-600 dark:text-sky-400" />
                        </div>
                        <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">Ubicación</span>
                    </div>
                    <dl class="grid flex-1 grid-cols-2 content-start gap-x-4 gap-y-4 px-5 py-4">
                        <div class="col-span-2">
                            <dt class="{{ $labelClasses }}">Dirección</dt>
                            <dd class="{{ $valueClasses }}">{{ $ubicacion['direccion'] ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="{{ $labelClasses }}">Municipio</dt>
                            <dd class="{{ $valueClasses }}">{{ $ubicacion['municipio'] ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="{{ $labelClasses }}">Barrio</dt>
                            <dd class="{{ $valueClasses }}">{{ $ubicacion['barrio'] ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="{{ $labelClasses }}">Latitud</dt>
                            <dd class="{{ $valueClasses }} font-mono">{{ number_format($resultado['coordenadas']['lat'], 6) }}</dd>
                        </div>
                        <div>
                            <dt class="{{ $labelClasses }}">Longitud</dt>
                            <dd class="{{ $valueClasses }} font-mono">{{ number_format($resultado['coordenadas']['lng'], 6) }}</dd>
                        </div>
                    </dl>
                </div>

                {{-- Servicio --}}
                <div class="{{ $cardClasses }}">
                    <div class="flex items-center gap-2.5 border-b border-gray-100 px-5 py-3.5 dark:border-gray-800">
                        <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-amber-100 dark:bg-amber-900/40">
                            <flux:icon.bolt class="h-4 w-4 text-amber-600 dark:text-amber-400" />
                        </div>
                        <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">Servicio</span>
                    </div>
                    <dl class="grid flex-1 grid-cols-2 content-start gap-x-4 gap-y-4 px-5 py-4">
                        <div>
                            <dt class="{{ $labelClasses }}">Estado</dt>
                            <dd class="mt-1">
                                @if ($servicio['estado'] ?? null)
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $estadoClases }}">{{ $servicio['estado'] }}</span>
                                @else
                                    <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">—</span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="{{ $labelClasses }}">Estado de corte</dt>
                            <dd class="{{ $valueClasses }}">{{ $servicio['estado_corte'] ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="{{ $labelClasses }}">Categoría</dt>
                            <dd class="{{ $valueClasses }}">{{ $servicio['categoria'] ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="{{ $labelClasses }}">TAG</dt>
                            <dd class="{{ $valueClasses }}">{{ $ubicacion['tag'] ?? '—' }}</dd>
                        </div>
                        <div class="col-span-2">
                            <dt class="{{ $labelClasses }}">Descripción</dt>
                            <dd class="{{ $valueClasses }}">{{ $servicio['descripcion'] ?? '—' }}</dd>
                        </div>
                        <div class="col-span-2 grid grid-cols-3 gap-x-4">
                            <div>
                                <dt class="{{ $labelClasses }}">Estrato</dt>
                                <dd class="{{ $valueClasses }}">{{ $servicio['estrato'] ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="{{ $labelClasses }}">Ciclo</dt>
                                <dd class="{{ $valueClasses }}">{{ $servicio['ciclo'] ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="{{ $labelClasses }}">Anillado</dt>
                                <dd class="{{ $valueClasses }}">{{ $anillado ?? '—' }}</dd>
                            </div>
                        </div>
                    </dl>
                </div>
            </div>

            {{-- Fila 2: Medidor y Consumos --}}
            <div class="grid gap-4 md:grid-cols-2">

                {{-- Medidor --}}
                <div class="{{ $cardClasses }}">
                    <div class="flex items-center gap-2.5 border-b border-gray-100 px-5 py-3.5 dark:border-gray-800">
                        <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-violet-100 dark:bg-violet-900/40">
                            <flux:icon.cog-6-tooth class="h-4 w-4 text-violet-600 dark:text-violet-400" />
                        </div>
                        <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">Medidor</span>
                    </div>
                    <dl class="grid flex-1 grid-cols-2 content-start gap-x-4 gap-y-4 px-5 py-4">
                        <div class="col-span-2">
                            <dt class="{{ $labelClasses }}">Serie actual</dt>
                            <dd class="{{ $valueClasses }} font-mono">{{ $medidor['serie_actual'] ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="{{ $labelClasses }}">Serie anterior</dt>
                            <dd class="{{ $valueClasses }} font-mono">{{ $medidor['serie_anterior'] ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="{{ $labelClasses }}">Fecha anterior</dt>
                            <dd class="{{ $valueClasses }}">{{ $medidor['fecha_anterior'] ?? '—' }}</dd>
                        </div>
                    </dl>
                </div>

                {{-- Consumos --}}
                <div class="{{ $cardClasses }}">
                    <div class="flex items-center gap-2.5 border-b border-gray-100 px-5 py-3.5 dark:border-gray-800">
                        <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-100 dark:bg-emerald-900/40">
                            <flux:icon.chart-bar class="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                        </div>
                        <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">Consumos — últimos 6 meses</span>
                    </div>
                    <div class="flex flex-1 flex-col justify-center px-5 py-4">
                        @if (count($consumosFiltrados))
                            <div class="space-y-3">
                                @foreach ($consumosFiltrados as $mes => $consumo)
                                    @php $pct = $maxConsumo > 0 ? round(intval($consumo) / $maxConsumo * 100) : 0; @endphp
                                    <div class="flex items-center gap-3">
                                        <span class="w-8 shrink-0 text-right text-xs font-semibold text-gray-400 dark:text-gray-500">{{ $mes }}</span>
                                        <div class="flex-1 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                                            <div @class([
                                                    'h-2.5 rounded-full transition-all duration-500',
                                                    'bg-red-400'   => $pct >= 80,
                                                    'bg-amber-400' => $pct >= 50 && $pct < 80,
                                                    'bg-emerald-400' => $pct < 50,
                                                ])
                                                style="width: {{ $pct }}%"></div>
                                        </div>
                                        <span class="w-20 shrink-0 text-right text-xs font-semibold tabular-nums text-gray-700 dark:text-gray-200">
                                            {{ number_format(intval($consumo)) }} m³
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="flex flex-col items-center justify-center py-4 text-center">
                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                                    <flux:icon.chart-bar class="h-5 w-5 text-gray-400" />
                                </div>
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Sin datos de consumo registrados</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

        </div>
    @endif

    <x-filament-actions::modals />
</div>
