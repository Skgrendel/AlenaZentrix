<div class="space-y-6">
    <div>
        <flux:heading size="lg">Envío diario del Excel por correo</flux:heading>
        <flux:text class="mt-1 text-sm">
            Cada día, a la hora que elijas, el sistema envía por correo el Excel de la auditoría de suspensiones con los
            registros de ese día, de las 00:00 hasta la hora del envío. La hora es la de Colombia.
        </flux:text>
    </div>

    <form wire:submit="guardar" class="space-y-5">
        <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:switch wire:model.live="activo" label="Enviar el Excel automáticamente" align="left"
                description="Si está apagado no se envía nada, aunque estén llenos los demás campos." />
        </div>

        <div class="grid items-start gap-4 sm:grid-cols-2">
            <div>
                <flux:input type="time" wire:model="hora" label="Hora del envío" />
                @error('hora') <flux:text class="mt-1 text-xs text-red-600">{{ $message }}</flux:text> @enderror
                <flux:text class="mt-1 text-xs">Hora de Colombia. Los registros que lleguen después de esta hora no entran en el correo del día.</flux:text>
            </div>

            <div>
                <flux:checkbox.group wire:model="dias" label="Días en que se envía">
                    <div class="mt-1 grid grid-cols-2 gap-x-4 gap-y-1">
                        @foreach ($dias as $numero => $nombre)
                            <flux:checkbox value="{{ $numero }}" label="{{ $nombre }}" />
                        @endforeach
                    </div>
                </flux:checkbox.group>
                @error('dias') <flux:text class="mt-1 text-xs text-red-600">{{ $message }}</flux:text> @enderror
            </div>
        </div>

        <div>
            <flux:textarea wire:model="destinatarios" label="Correos que reciben el Excel" rows="3" placeholder="uno@empresa.com&#10;otro@empresa.com" />
            @error('destinatarios') <flux:text class="mt-1 text-xs text-red-600">{{ $message }}</flux:text> @enderror
            <flux:text class="mt-1 text-xs">
                Uno por línea o separados por coma, hasta 10. El Excel trae contratos y nombres de técnicos: envíalo solo a personas autorizadas.
            </flux:text>
        </div>

        <div>
            <flux:input wire:model="asunto" label="Asunto" />
            @error('asunto') <flux:text class="mt-1 text-xs text-red-600">{{ $message }}</flux:text> @enderror
            <flux:text class="mt-1 text-xs">Puedes usar <code>{fecha}</code> y <code>{registros}</code>: se reemplazan por la fecha del día y la cantidad de registros.</flux:text>
        </div>

        <div>
            <flux:textarea wire:model="mensaje" label="Mensaje del correo" rows="5" />
            @error('mensaje') <flux:text class="mt-1 text-xs text-red-600">{{ $message }}</flux:text> @enderror
            <flux:text class="mt-1 text-xs">Debajo del mensaje el correo muestra el resumen del día (registros, con errores y con alertas).</flux:text>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <flux:button type="submit" variant="primary" icon="check">Guardar</flux:button>
            <flux:button type="button" wire:click="enviarPrueba" icon="paper-airplane" wire:loading.attr="disabled" wire:target="enviarPrueba">
                <span wire:loading.remove wire:target="enviarPrueba">Enviarme una prueba</span>
                <span wire:loading wire:target="enviarPrueba">Enviando…</span>
            </flux:button>
        </div>
        <flux:text class="text-xs">La prueba se envía solo a tu correo, con lo que ves en pantalla y los registros de hoy.</flux:text>
    </form>

    <flux:separator />

    <div class="space-y-3">
        <flux:heading>Últimos envíos</flux:heading>

        @forelse ($envios as $envio)
            <div class="flex flex-wrap items-start justify-between gap-2 rounded-lg border border-zinc-200 p-3 text-sm dark:border-zinc-700" wire:key="envio-{{ $envio->id }}">
                <div>
                    <div class="font-medium">
                        {{ $envio->created_at->format('d/m/Y h:i a') }}
                        <span class="text-xs font-normal text-zinc-500">· {{ $envio->origen === 'prueba' ? 'prueba' : 'automático' }}</span>
                    </div>
                    <div class="text-xs text-zinc-500">{{ implode(', ', $envio->destinatarios) }} · {{ $envio->registros }} registro(s)</div>
                    @if ($envio->error)
                        <div class="mt-1 text-xs text-red-600">{{ $envio->error }}</div>
                    @endif
                </div>
                <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $envio->estado === 'enviado' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                    {{ $envio->estado === 'enviado' ? 'Enviado' : 'Falló' }}
                </span>
            </div>
        @empty
            <flux:text class="text-sm">Todavía no se ha enviado nada.</flux:text>
        @endforelse
    </div>
</div>
