<div class="max-w-4xl mx-auto py-8 px-4">
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">Importar Revisión de Medidores</flux:heading>
            <flux:subheading>Carga masiva de órdenes desde archivo CSV o Excel.</flux:subheading>
        </div>
        <div class="flex gap-3">
            <flux:button href="{{ route('operaciones.revision-medidores.plantilla') }}" icon="arrow-down-tray" variant="ghost">
                Descargar Plantilla
            </flux:button>
            <flux:button href="{{ route('operaciones.revision-medidores.index') }}" icon="arrow-left" variant="ghost">
                Volver al listado
            </flux:button>
        </div>
    </div>

    @if ($importacionRealizada)
        <div class="mb-6 p-4 rounded-lg border {{ count($errores) === 0 ? 'bg-green-50 border-green-200' : 'bg-yellow-50 border-yellow-200' }}">
            <p class="font-semibold text-sm">
                {{ $importadas }} órdenes importadas
                &bull;
                {{ count($errores) }} errores
            </p>
        </div>

        @if (count($errores) > 0)
            <div class="mb-6">
                <flux:heading size="sm" class="mb-3">Filas con errores</flux:heading>
                <table class="w-full text-sm border border-gray-200 rounded">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left">Fila</th>
                            <th class="px-3 py-2 text-left">Campo</th>
                            <th class="px-3 py-2 text-left">Motivo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($errores as $error)
                            <tr class="border-t border-gray-100">
                                <td class="px-3 py-2">{{ $error['fila'] }}</td>
                                <td class="px-3 py-2 font-mono text-xs">{{ $error['campo'] }}</td>
                                <td class="px-3 py-2 text-red-600">{{ $error['motivo'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <flux:button wire:click="$set('importacionRealizada', false)" variant="outline">
            Importar otro archivo
        </flux:button>
    @else
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <flux:heading size="sm" class="mb-1">Cargar archivo</flux:heading>
            <p class="text-sm text-gray-500 mb-4">
                Columnas requeridas: <code>contrato, cliente, direccion, barrio, medidor</code>
            </p>

            <form wire:submit="importar">
                {{ $this->form }}

                <div class="mt-4">
                    <flux:button type="submit" variant="primary" icon="arrow-up-tray" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="importar">Importar Órdenes</span>
                        <span wire:loading wire:target="importar">Procesando...</span>
                    </flux:button>
                </div>
            </form>
        </div>
    @endif

    <x-filament-actions::modals />
</div>
