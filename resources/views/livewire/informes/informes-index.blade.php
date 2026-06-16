<div class="space-y-6">
    <div>
        <flux:heading size="xl">Informes</flux:heading>
        <flux:text class="mt-1 text-sm">Genera vistas previas y descarga informes en formato Excel.</flux:text>
    </div>

    {{-- Panel de filtros --}}
    <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900 space-y-5">

        {{-- Filtros + Periodo en una sola fila --}}
        <div>
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-400 dark:text-zinc-500">Filtros</p>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">

                <div>
                    <label class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Servicio</label>
                    <select wire:model.live="servicio"
                        class="w-full rounded-lg border border-zinc-300 bg-white px-4 py-3 text-sm text-zinc-800 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-zinc-600 dark:bg-zinc-800 dark:text-white">
                        <option value="">Todos los servicios</option>
                        @foreach($servicioOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                @if(count($subTipoOptions) > 0)
                <div>
                    <label class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Sub-tipo</label>
                    <select wire:model="subTipo"
                        class="w-full rounded-lg border border-zinc-300 bg-white px-4 py-3 text-sm text-zinc-800 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-zinc-600 dark:bg-zinc-800 dark:text-white">
                        <option value="">Todos los sub-tipos</option>
                        @foreach($subTipoOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                @endif

                <div>
                    <label class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Estado</label>
                    <select wire:model="estado"
                        class="w-full rounded-lg border border-zinc-300 bg-white px-4 py-3 text-sm text-zinc-800 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-zinc-600 dark:bg-zinc-800 dark:text-white">
                        <option value="">Todos los estados</option>
                        @foreach($estadoOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                @if(count($procesoOptions) > 0)
                <div>
                    <label class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Proceso</label>
                    <select wire:model="proceso"
                        class="w-full rounded-lg border border-zinc-300 bg-white px-4 py-3 text-sm text-zinc-800 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-zinc-600 dark:bg-zinc-800 dark:text-white">
                        <option value="">Todos los procesos</option>
                        @foreach($procesoOptions as $id => $nombre)
                            <option value="{{ $id }}">{{ $nombre }}</option>
                        @endforeach
                    </select>
                </div>
                @endif

                <div>
                    <label class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Fecha inicial</label>
                    <input type="date" wire:model="fechaInicio"
                        class="w-full rounded-lg border border-zinc-300 bg-white px-4 py-3 text-sm text-zinc-800 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-zinc-600 dark:bg-zinc-800 dark:text-white">
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Fecha final</label>
                    <input type="date" wire:model="fechaFin"
                        class="w-full rounded-lg border border-zinc-300 bg-white px-4 py-3 text-sm text-zinc-800 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-zinc-600 dark:bg-zinc-800 dark:text-white">
                </div>

            </div>
        </div>

    </div>

    {{-- Botones centrados --}}
    <div class="flex justify-center gap-4">
        <button wire:click="generarVista" wire:loading.attr="disabled"
            class="inline-flex items-center gap-2 rounded-lg bg-emerald-500 px-8 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-600 disabled:opacity-60">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            </svg>
            <span wire:loading.remove wire:target="generarVista">Generar Vista Previa</span>
            <span wire:loading wire:target="generarVista">Generando...</span>
        </button>

        <button wire:click="download" wire:loading.attr="disabled"
            class="inline-flex items-center gap-2 rounded-lg bg-sky-500 px-8 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-600 disabled:opacity-60">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
            </svg>
            <span wire:loading.remove wire:target="download">Descargar Excel</span>
            <span wire:loading wire:target="download">Preparando...</span>
        </button>
    </div>

    {{-- Tabla de vista previa --}}
    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 bg-zinc-100/70 dark:border-zinc-700 dark:bg-zinc-800/60">
                        @if($previewGenerado && count($preview) > 0)
                            @foreach(array_keys($preview[0]) as $header)
                            <th class="px-5 py-3 text-left text-xs font-semibold text-zinc-500 dark:text-zinc-400">
                                {{ $header }}
                            </th>
                            @endforeach
                        @else
                            <th class="px-5 py-3 text-left text-xs font-semibold text-zinc-400">—</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @if(! $previewGenerado)
                        <tr>
                            <td class="px-5 py-12 text-center text-sm text-zinc-400">
                                Aplica los filtros y presiona <span class="font-medium text-zinc-500">Generar Vista Previa</span> para ver los resultados.
                            </td>
                        </tr>
                    @elseif(count($preview) === 0)
                        <tr>
                            <td class="px-5 py-12 text-center text-sm text-zinc-400">
                                Sin registros para los filtros seleccionados.
                            </td>
                        </tr>
                    @else
                        @foreach($preview as $row)
                        <tr class="border-b border-zinc-100 transition-colors last:border-0 hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-800/40">
                            @foreach($row as $cell)
                            <td class="px-5 py-3 text-zinc-600 dark:text-zinc-300 {{ $loop->first ? 'font-semibold text-blue-600 dark:text-blue-400' : '' }}">
                                {{ $cell }}
                            </td>
                            @endforeach
                        </tr>
                        @endforeach
                    @endif
                </tbody>
            </table>
        </div>

        @if($previewGenerado && count($preview) > 0)
        <div class="border-t border-zinc-100 bg-zinc-50/50 px-5 py-2.5 dark:border-zinc-800 dark:bg-zinc-800/30">
            <p class="text-xs text-zinc-400">
                @if(count($preview) === 50)
                    Mostrando los primeros <span class="font-medium text-zinc-500">50</span> registros — descarga el Excel para el total.
                @else
                    <span class="font-medium text-zinc-500">{{ count($preview) }}</span> {{ count($preview) === 1 ? 'registro encontrado' : 'registros encontrados' }}.
                @endif
            </p>
        </div>
        @endif
    </div>

</div>
