<div class="space-y-6">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Editar Reporte</flux:heading>
            <flux:text class="mt-1 text-sm">
                Orden: <span class="font-semibold">{{ $orden->numero_orden }}</span>   @if ($orden->cliente)
                    —{{ $orden->cliente }}
                @endif
                <flux:badge color="red" class="ml-2">Rechazada</flux:badge>
            </flux:text>
        </div>
        <flux:button href="{{ route('tecnico.mis-ordenes') }}" icon="arrow-left" variant="ghost" wire:navigate>
            Volver
        </flux:button>
    </div>

    @if($reporte->comentarios)
    {{-- Motivo de rechazo --}}
    <div class="rounded-lg border border-red-200 bg-red-50 p-5 dark:border-red-800 dark:bg-red-900/20">
        <h3 class="mb-2 text-sm font-semibold uppercase tracking-wide text-red-600 dark:text-red-400">Motivo de Rechazo</h3>
        <p class="text-sm text-red-700 dark:text-red-300">{{ $reporte->comentarios }}</p>
    </div>
    @endif

     {{-- Datos del punto de servicio (solo lectura) --}}
    @if ($orden->direccion && $orden->barrio && $orden->contrato && $orden->medidor && $orden->cliente)
        <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Punto de
                Servicio</h3>
            <dl class="grid grid-cols-2 gap-x-6 gap-y-2 sm:grid-cols-3">
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Cliente</dt>
                    <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $orden->cliente }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Contrato</dt>
                    <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $orden->contrato }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Medidor</dt>
                    <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $orden->medidor }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Tipo de Medidor</dt>
                    <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $orden->tipo_medidor }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Dirección</dt>
                    <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $orden->direccion }},
                        {{ $orden->barrio }}</dd>
                </div>
            </dl>
        </div>
    @endif
    @if ($orden->antecedente)
        <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Antecedentes
            </h3>
            <p class="text-sm text-zinc-900 dark:text-white">{{ $orden->antecedente }}</p>
        </div>
    @endif
    @if ($orden->objetivo)
        <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Objetivo
            </h3>
            <p class="text-sm text-zinc-900 dark:text-white">{{ $orden->objetivo }}</p>
        </div>
    @endif
    @if (!empty($orden->actividades))
        <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Actividades
            </h3>
            <ul class="list-disc space-y-1 pl-5">
                @foreach ($orden->actividades as $actividad)
                    <li class="text-sm text-zinc-900 dark:text-white">{{ $actividad['descripcion'] ?? '' }}</li>
                @endforeach
            </ul>
        </div>
    @endif

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
                <span wire:loading.remove>Reenviar Reporte</span>
                <span wire:loading>Enviando...</span>
            </flux:button>
        </div>
    </form>

</div>
