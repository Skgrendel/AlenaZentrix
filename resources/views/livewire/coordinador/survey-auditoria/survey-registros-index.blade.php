<div class="space-y-4">
    <div>
        <flux:heading size="xl">Auditoría de Suspensiones</flux:heading>
        <flux:text class="mt-1 text-sm">Suspensiones reportadas por los técnicos en campo. Asigna un auditor de campo para validarlas en sitio (plazo máximo de 48 horas).</flux:text>
    </div>

    {{-- Estado de la sincronización: no bloquea nada y se actualiza solo mientras corre. --}}
    @php
        $estado = $sincronizacion['estado'] ?? null;
        $fin = ! empty($sincronizacion['fin']) ? \Illuminate\Support\Carbon::parse($sincronizacion['fin']) : null;
        $inicio = ! empty($sincronizacion['inicio']) ? \Illuminate\Support\Carbon::parse($sincronizacion['inicio']) : null;
        $minutosDeEspera = (int) ceil($esperaSegundos / 60);
    @endphp
    <div
        @if ($sincronizando) wire:poll.3s="verificarSincronizacion" @endif
        data-test="estado-sincronizacion"
        class="flex flex-wrap items-center gap-x-4 gap-y-1 rounded-lg border px-4 py-3 text-sm
            {{ $sincronizando ? 'border-blue-200 bg-blue-50 text-blue-900 dark:border-blue-900 dark:bg-blue-900/20 dark:text-blue-200' : '' }}
            {{ ! $sincronizando && $estado === 'error' ? 'border-red-200 bg-red-50 text-red-900 dark:border-red-900 dark:bg-red-900/20 dark:text-red-200' : '' }}
            {{ ! $sincronizando && $estado !== 'error' ? 'border-zinc-200 bg-white text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300' : '' }}"
    >
        @if ($sincronizando)
            <svg class="size-4 shrink-0 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
            </svg>
            <span class="font-semibold">Sincronizando registros…</span>
            <span>{{ $sincronizacion['etapa'] ?? 'En cola' }}</span>
            <span class="text-xs opacity-80">
                {{ $sincronizacion['nuevos'] ?? 0 }} nuevos · {{ $sincronizacion['actualizados'] ?? 0 }} actualizados · {{ $sincronizacion['fotos'] ?? 0 }} fotos
                @if ($inicio) · iniciada {{ $inicio->diffForHumans() }} @endif
            </span>
            @if (($sincronizacion['estado'] ?? null) === 'en_cola' && $inicio && $inicio->diffInSeconds(now()) > 60)
                <span class="text-xs">Sigue en cola. Si no avanza, avisa a soporte para que revisen el proceso en segundo plano.</span>
            @endif
        @elseif ($estado === 'error')
            <span class="font-semibold">La última sincronización falló</span>
            <span>{{ $sincronizacion['mensaje'] ?? '' }}</span>
            @if ($fin) <span class="text-xs opacity-80">{{ $fin->diffForHumans() }}</span> @endif
        @elseif ($estado === 'terminado')
            <span class="size-2 shrink-0 rounded-full bg-green-500" aria-hidden="true"></span>
            <span class="font-semibold">Última sincronización {{ $fin?->diffForHumans() }}</span>
            <span class="text-xs opacity-80">
                {{ $sincronizacion['nuevos'] ?? 0 }} nuevos · {{ $sincronizacion['actualizados'] ?? 0 }} actualizados · {{ $sincronizacion['fotos'] ?? 0 }} fotos copiadas
                @if (($sincronizacion['errores'] ?? 0) > 0) · {{ $sincronizacion['errores'] }} con error @endif
            </span>
        @else
            <span class="size-2 shrink-0 rounded-full bg-zinc-400" aria-hidden="true"></span>
            <span>Aún no se ha sincronizado desde esta pantalla.</span>
        @endif

        @if (! $sincronizando && $esperaSegundos > 0)
            <span class="ml-auto text-xs opacity-80">Podrás volver a sincronizar en {{ $minutosDeEspera }} min.</span>
        @endif
    </div>

    <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
        {{ $this->table }}
    </div>

    <x-filament-actions::modals />
</div>
