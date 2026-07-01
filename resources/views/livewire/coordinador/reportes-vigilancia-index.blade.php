<div class="space-y-6">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div class="space-y-2">
            <flux:heading size="xl">Reportes de Vigilancia</flux:heading>
            <flux:text class="text-sm flex items-center gap-2 flex-wrap">
                <span>Orden: <span class="font-semibold">{{ $orden->numero_orden }}</span></span>
                @if($orden->cliente)
                    <span class="text-zinc-400">·</span>
                    <span>{{ $orden->cliente }}</span>
                @endif
                <flux:badge color="success" size="sm">Dispositivos de Vigilancia</flux:badge>
                <flux:badge
                    color="{{ $orden->estado === \App\Enums\EstadoOrden::Completada ? 'green' : ($orden->estado === \App\Enums\EstadoOrden::EnRevision ? 'purple' : 'blue') }}"
                    size="sm"
                >
                    {{ $orden->estado->label() }}
                </flux:badge>
            </flux:text>
            <div class="flex flex-wrap gap-x-6 gap-y-1 text-sm text-zinc-600 dark:text-zinc-400">
                @if($orden->contrato)
                    <span><span class="font-medium text-zinc-400 uppercase text-xs tracking-wide">Contrato:</span> {{ $orden->contrato }}</span>
                @endif
                @if($orden->direccion)
                    <span><span class="font-medium text-zinc-400 uppercase text-xs tracking-wide">Dirección:</span> {{ $orden->direccion }}</span>
                @endif
                <span>
                    <span class="font-medium text-zinc-400 uppercase text-xs tracking-wide">Investigador:</span>
                    {{ $orden->personal ? "{$orden->personal->nombres} {$orden->personal->apellidos}" : '—' }}
                </span>
            </div>
        </div>
        <div class="flex items-center gap-2">
            @can('informes')
                <flux:button
                    href="{{ route('operaciones.ordenes-trabajo.reporte-vigilancia', $orden) }}"
                    icon="arrow-down-tray" variant="filled" target="_blank">
                    Descargar Informe PDF
                </flux:button>
            @endcan
            <flux:button href="{{ route('operaciones.ordenes-trabajo') }}" icon="arrow-left" variant="ghost" wire:navigate>
                Volver
            </flux:button>
        </div>
    </div>

    @if($orden->estado === \App\Enums\EstadoOrden::EnRevision)

        {{-- Tabla + panel de decisión side by side --}}
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

            <div class="lg:col-span-2">
                <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                    {{ $this->table }}
                </div>
            </div>

            {{-- Panel de decisión --}}
            <div>
                <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900"
                     x-data="{ rechazo: @entangle('mostrarFormRechazo') }">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Decisión</h3>

                    <div x-show="!rechazo" class="flex flex-col gap-3">
                        <div class="[&>button]:w-full [&>button]:justify-center">
                            {{ $this->completarAction }}
                        </div>
                        <flux:button
                            variant="danger"
                            icon="x-circle"
                            class="w-full justify-center"
                            @click="rechazo = true"
                        >
                            Rechazar Orden
                        </flux:button>
                    </div>

                    <div x-show="rechazo" x-cloak>
                        <p class="mb-3 text-sm text-zinc-600 dark:text-zinc-400">
                            Indica el motivo del rechazo. El investigador podrá agregar nuevos reportes.
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

    @else

        {{-- Solo tabla --}}
        <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            {{ $this->table }}
        </div>

    @endif

    <x-filament-actions::modals />
</div>
