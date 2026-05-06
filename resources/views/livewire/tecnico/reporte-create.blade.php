<div class="space-y-6">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Crear Reporte</flux:heading>
            <flux:text class="mt-1 text-sm">Orden: <span class="font-semibold">{{ $orden->numero_orden }}</span> — {{ $orden->cliente }}</flux:text>
        </div>
        <flux:button href="{{ route('tecnico.mis-ordenes') }}" icon="arrow-left" variant="ghost" wire:navigate>
            Volver
        </flux:button>
    </div>

    {{-- Datos del punto de servicio (solo lectura) --}}
    <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Punto de Servicio</h3>
        <dl class="grid grid-cols-2 gap-x-6 gap-y-2 sm:grid-cols-3">
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Contrato</dt>
                <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $orden->contrato }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Medidor</dt>
                <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $orden->medidor }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Dirección</dt>
                <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $orden->direccion }}, {{ $orden->barrio }}</dd>
            </div>
        </dl>
    </div>

    {{-- GPS silencioso: captura en segundo plano, modal si está bloqueado --}}
    <div
        x-data="{
            modalAbierto: false,
            captureLocation() {
                if (!navigator.geolocation) {
                    this.modalAbierto = true;
                    return;
                }
                navigator.geolocation.getCurrentPosition(
                    (pos) => {
                        $wire.set('latitud', String(pos.coords.latitude));
                        $wire.set('longitud', String(pos.coords.longitude));
                    },
                    () => { this.modalAbierto = true; },
                    { enableHighAccuracy: true, timeout: 15000 }
                );
            }
        }"
        x-init="captureLocation()"
    >
        {{-- Modal permiso de ubicación --}}
        <div
            x-show="modalAbierto"
            x-transition.opacity
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
            style="display: none"
        >
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-2xl dark:bg-zinc-900">
                <div class="mb-4 flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/40">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-semibold text-zinc-900 dark:text-white">Permiso de ubicación requerido</h3>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">Se necesita para registrar el punto de atención</p>
                    </div>
                </div>
                <p class="mb-5 text-sm text-zinc-600 dark:text-zinc-300">
                    Para enviar el reporte es necesario que actives la ubicación en tu navegador. Haz clic en el ícono de candado en la barra de dirección y permite el acceso a la ubicación, luego intenta de nuevo.
                </p>
                <div class="flex justify-end gap-3">
                    <flux:button variant="ghost" @click="modalAbierto = false">Cancelar</flux:button>
                    <flux:button variant="primary" @click="modalAbierto = false; captureLocation()">Reintentar</flux:button>
                </div>
            </div>
        </div>
    </div>

    {{-- Formulario --}}
    <form wire:submit="submit" class="space-y-6">
        <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            {{ $this->form }}
        </div>

        <div class="flex justify-end gap-3">
            <flux:button href="{{ route('tecnico.mis-ordenes') }}" variant="ghost" wire:navigate>
                Cancelar
            </flux:button>
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                <span wire:loading.remove>Enviar Reporte</span>
                <span wire:loading>Enviando...</span>
            </flux:button>
        </div>
    </form>

    <x-filament-actions::modals />

</div>
