<div class="space-y-6">

    {{-- Encabezado --}}
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Editar Rol</flux:heading>
            <flux:text class="mt-1 text-sm">Configura el nombre y los permisos del rol.</flux:text>
        </div>
        <flux:button href="{{ route('admin.roles') }}" icon="arrow-left" variant="ghost" wire:navigate>
            Volver
        </flux:button>
    </div>

    @if (session('success'))
        <div class="rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700 dark:bg-green-900/30 dark:text-green-400">
            {{ session('success') }}
        </div>
    @endif

    <form wire:submit="guardar" class="space-y-6">

        {{-- Nombre del rol --}}
        <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
            <h3 class="mb-4 text-sm font-semibold text-zinc-700 dark:text-zinc-300">Nombre del Rol</h3>
            <input
                type="text"
                wire:model="name"
                @if($esAdministrador) disabled @endif
                class="w-full max-w-xl rounded-lg border border-zinc-300 bg-white px-4 py-2.5 text-sm text-zinc-800 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 disabled:cursor-not-allowed disabled:opacity-60 dark:border-zinc-600 dark:bg-zinc-800 dark:text-white"
            >
            @error('name')
                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
            @enderror
            @if($esAdministrador)
                <p class="mt-1 text-xs text-zinc-400">El rol Administrador no puede ser renombrado.</p>
            @endif
        </div>

        {{-- Permisos agrupados por módulo --}}
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2 xl:grid-cols-3">
            @foreach($permisosPorModulo as $grupo)
            <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <h3 class="mb-4 text-sm font-semibold text-zinc-700 dark:text-zinc-300">
                    {{ $grupo['modulo'] }}
                </h3>
                <div class="space-y-2.5">
                    @foreach($grupo['permisos'] as $permiso)
                    <label class="flex cursor-pointer items-start gap-3">
                        <input
                            type="checkbox"
                            wire:model="selectedPermissions"
                            value="{{ $permiso['name'] }}"
                            class="mt-0.5 h-4 w-4 rounded border-zinc-300 text-blue-600 focus:ring-blue-500 dark:border-zinc-600"
                        >
                        <span class="text-sm text-zinc-600 dark:text-zinc-300">{{ $permiso['description'] }}</span>
                    </label>
                    @endforeach
                </div>
            </div>
            @endforeach
        </div>

        {{-- Botón guardar --}}
        <div class="flex justify-end">
            <button type="submit"
                class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 disabled:opacity-60"
                wire:loading.attr="disabled">
                <svg wire:loading.remove wire:target="guardar" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
                <svg wire:loading wire:target="guardar" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>
                <span wire:loading.remove wire:target="guardar">Guardar cambios</span>
                <span wire:loading wire:target="guardar">Guardando...</span>
            </button>
        </div>

    </form>
</div>
