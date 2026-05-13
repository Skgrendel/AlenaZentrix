<div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl p-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">Empresas</flux:heading>
        {{ $this->createAction }}
    </div>
    {{ $this->table }}
    <x-filament-actions::modals />
</div>
