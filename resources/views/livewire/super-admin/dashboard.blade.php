<div class="space-y-6">

    {{-- Header --}}
    <div>
        <flux:heading size="xl">Panel Superadmin</flux:heading>
        <flux:text class="mt-1 text-sm text-zinc-500">Vista global de la plataforma · {{ now()->isoFormat('D [de] MMMM [de] YYYY') }}</flux:text>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <flux:card class="flex items-center gap-4">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900">
                <flux:icon.building-office class="size-5 text-blue-600 dark:text-blue-400" />
            </div>
            <div>
                <p class="text-2xl font-bold leading-none">{{ $this->totalEmpresas }}</p>
                <flux:text class="mt-0.5 text-xs text-zinc-500">Empresas · <span class="text-green-600">{{ $this->empresasActivas }} activas</span></flux:text>
            </div>
        </flux:card>

        <flux:card class="flex items-center gap-4">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-violet-100 dark:bg-violet-900">
                <flux:icon.users class="size-5 text-violet-600 dark:text-violet-400" />
            </div>
            <div>
                <p class="text-2xl font-bold leading-none">{{ $this->totalUsuarios }}</p>
                <flux:text class="mt-0.5 text-xs text-zinc-500">Usuarios registrados</flux:text>
            </div>
        </flux:card>

        <flux:card class="flex items-center gap-4">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-100 dark:bg-amber-900">
                <flux:icon.clipboard-document-list class="size-5 text-amber-600 dark:text-amber-400" />
            </div>
            <div>
                <p class="text-2xl font-bold leading-none">{{ $this->totalOrdenes }}</p>
                <flux:text class="mt-0.5 text-xs text-zinc-500">Órdenes totales</flux:text>
            </div>
        </flux:card>

        <flux:card class="flex items-center gap-4">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-green-100 dark:bg-green-900">
                <flux:icon.chart-bar class="size-5 text-green-600 dark:text-green-400" />
            </div>
            <div>
                <p class="text-2xl font-bold leading-none">{{ $this->ordenesMes }}</p>
                <flux:text class="mt-0.5 text-xs text-zinc-500">Órdenes este mes</flux:text>
            </div>
        </flux:card>
    </div>

    {{-- Tabla de empresas --}}
    <flux:card class="p-0">
        <div class="flex items-center justify-between border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
            <div>
                <flux:heading size="sm">Empresas</flux:heading>
                <flux:text class="text-xs text-zinc-500">Resumen por empresa</flux:text>
            </div>
            <flux:button href="{{ route('superadmin.companies') }}" size="sm" variant="ghost" wire:navigate>
                Ver todas
            </flux:button>
        </div>

        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-zinc-100 bg-zinc-50 text-left text-xs font-medium text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800/50">
                    <th class="px-4 py-2.5">Empresa</th>
                    <th class="px-4 py-2.5 text-center">Estado</th>
                    <th class="px-4 py-2.5 text-center">Usuarios</th>
                    <th class="px-4 py-2.5 text-center">Órdenes</th>
                    <th class="px-4 py-2.5 text-right">Creada</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700">
                @forelse($this->empresas as $empresa)
                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/30">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                @if($empresa->logo)
                                    <img src="{{ $empresa->logo }}" alt="{{ $empresa->nombre }}" class="h-7 w-7 rounded object-contain">
                                @else
                                    <div class="flex h-7 w-7 items-center justify-center rounded bg-zinc-200 text-xs font-bold text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300">
                                        {{ mb_strtoupper(mb_substr($empresa->nombre, 0, 1)) }}
                                    </div>
                                @endif
                                <span class="font-medium text-zinc-800 dark:text-zinc-100">{{ $empresa->nombre }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($empresa->activo)
                                <flux:badge color="green" size="sm">Activa</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm">Inactiva</flux:badge>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            <flux:badge color="blue" size="sm">{{ $empresa->users_count }}</flux:badge>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <flux:badge color="amber" size="sm">{{ $empresa->orden_jobs_count }}</flux:badge>
                        </td>
                        <td class="px-4 py-3 text-right text-xs text-zinc-500">
                            {{ $empresa->created_at->format('d/m/Y') }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-zinc-400">No hay empresas registradas.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </flux:card>

</div>
