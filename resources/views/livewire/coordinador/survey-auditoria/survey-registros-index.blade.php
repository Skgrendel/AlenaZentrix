<div class="space-y-4">
    <div>
        <flux:heading size="xl">Auditoría de Suspensiones</flux:heading>
        <flux:text class="mt-1 text-sm">Suspensiones reportadas por los técnicos en Survey123. Asigna un auditor de campo para validarlas en sitio (plazo máximo de 48 horas).</flux:text>
    </div>

    <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
        {{ $this->table }}
    </div>

    <x-filament-actions::modals />
</div>
