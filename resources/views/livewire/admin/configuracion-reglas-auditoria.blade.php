<div class="space-y-6">
    <div>
        <flux:heading size="lg">Reglas de la auditoría</flux:heading>
        <flux:text class="mt-1 text-sm">
            Elige qué tan grave es cada hallazgo. Lo que se puede corregir sin repetir la visita no tiene por qué impedir la
            aprobación: se aprueba pidiendo la corrección, y cuando llega la segunda ronda se confirma.
        </flux:text>
    </div>

    <div class="grid gap-3 rounded-lg border border-zinc-200 p-4 text-sm sm:grid-cols-3 dark:border-zinc-700">
        <div><span class="font-semibold">Bloqueante.</span> Cuenta como error y propone «No se aprueba».</div>
        <div><span class="font-semibold">Corregible.</span> Cuenta como alerta y propone «Se aprueba solicitando correcciones».</div>
        <div><span class="font-semibold">Según el caso.</span> Cada hallazgo conserva su gravedad: lo que falta es error y lo atípico es alerta.</div>
    </div>

    <form wire:submit="guardar" class="space-y-4">
        <div class="divide-y divide-zinc-100 rounded-lg border border-zinc-200 dark:divide-zinc-800 dark:border-zinc-700">
            @foreach ($reglas as $codigo => $regla)
                <div class="flex flex-wrap items-center justify-between gap-3 p-3" wire:key="regla-{{ $codigo }}">
                    <div class="min-w-0 flex-1">
                        <div class="text-sm font-medium">
                            {{ $regla['nombre'] }}
                            @if (isset($sugeridos[$codigo]))
                                <span class="ml-1 rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-normal text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">sugerido: {{ $opciones[$sugeridos[$codigo]] }}</span>
                            @endif
                        </div>
                        <div class="text-xs text-zinc-500">{{ $regla['detalle'] }}</div>
                    </div>
                    <div class="w-44 shrink-0">
                        <flux:select wire:model="niveles.{{ $codigo }}" aria-label="Nivel de {{ $regla['nombre'] }}">
                            @foreach ($opciones as $valor => $etiqueta)
                                <flux:select.option value="{{ $valor }}">{{ $etiqueta }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                </div>
            @endforeach
        </div>

        @error('niveles.*') <flux:text class="text-xs text-red-600">{{ $message }}</flux:text> @enderror

        <div class="flex flex-wrap items-center gap-2">
            <flux:button type="submit" variant="primary" icon="check">Guardar</flux:button>
            <flux:button type="button" wire:click="restablecerSugeridos" icon="arrow-uturn-left">Volver a los valores sugeridos</flux:button>
        </div>
        <flux:text class="text-xs">
            Al guardar, los registros ya cargados se auditan de nuevo con estas reglas, así los contadores, la lista y el Excel
            quedan al día. No cambia su estado ni sus fotos.
        </flux:text>
    </form>
</div>
