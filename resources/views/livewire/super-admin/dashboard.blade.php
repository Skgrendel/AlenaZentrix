<div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl p-6">
    <flux:heading size="xl">Panel Superadmin</flux:heading>
    <flux:text class="text-zinc-500">Vista global de la plataforma.</flux:text>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <flux:card>
            <flux:heading>Empresas</flux:heading>
            <p class="mt-2 text-3xl font-bold">{{ $this->totalEmpresas }}</p>
        </flux:card>
        <flux:card>
            <flux:heading>Usuarios</flux:heading>
            <p class="mt-2 text-3xl font-bold">{{ $this->totalUsuarios }}</p>
        </flux:card>
        <flux:card>
            <flux:heading>Órdenes</flux:heading>
            <p class="mt-2 text-3xl font-bold">{{ $this->totalOrdenes }}</p>
        </flux:card>
    </div>
</div>
