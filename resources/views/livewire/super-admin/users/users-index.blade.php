<div class="space-y-4">
    <div>
        <flux:heading size="xl">Usuarios</flux:heading>
        <flux:text class="mt-1 text-sm">Administra los usuarios del sistema.</flux:text>
    </div>

    <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
        {{ $this->table }}
    </div>

    <x-filament-actions::modals />
</div>
