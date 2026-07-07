<div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
                <flux:label>Desde</flux:label>
                <flux:input type="date" wire:model="desde" />
            </div>

            <div>
                <flux:label>Hasta</flux:label>
                <flux:input type="date" wire:model="hasta" />
            </div>
        </div>

        <div class="flex items-center gap-2">
            <flux:button wire:click="limpiar" variant="ghost" size="sm" icon="x-mark">
                Limpiar
            </flux:button>
            <flux:button wire:click="aplicar" variant="primary" size="sm" icon="magnifying-glass">
                Filtrar
            </flux:button>
        </div>
    </div>
</div>
