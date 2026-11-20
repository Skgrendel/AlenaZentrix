<div class="space-y-6">
    <div>
        <flux:heading size="lg">Revisión de fotos con IA</flux:heading>
        <flux:text class="mt-1 text-sm">
            Elige el modelo y escribe las instrucciones con las que se revisan las fotos de las suspensiones.
            La IA solo sugiere: no cambia el resultado de la auditoría y una persona decide.
        </flux:text>
    </div>

    <form wire:submit="guardar" class="space-y-5">
        <div class="grid items-start gap-4 sm:grid-cols-2">
            <div>
                <flux:select wire:model.live="modelo" label="Modelo">
                    @foreach (config('services.openai.modelos') as $nombre => $datos)
                        <flux:select.option value="{{ $nombre }}">{{ $nombre }}</flux:select.option>
                    @endforeach
                    @unless (array_key_exists($modelo, config('services.openai.modelos')))
                        <flux:select.option value="{{ $modelo }}">{{ $modelo }}</flux:select.option>
                    @endunless
                </flux:select>
                <flux:text class="mt-1 text-xs">
                    @if ($tarifa)
                        Tarifa de referencia: US$ {{ number_format($tarifa['entrada'], 2) }} por millón de tokens de entrada y
                        US$ {{ number_format($tarifa['salida'], 2) }} de salida. Confírmala en la tarifa vigente del proveedor.
                    @else
                        Este modelo no tiene tarifa registrada: la prueba no mostrará costo.
                    @endif
                </flux:text>
            </div>

            <div>
                <flux:select wire:model="detalle_imagen" label="Detalle con que se miran las fotos">
                    <flux:select.option value="low">Bajo: más barato, no alcanza a leer series ni lecturas</flux:select.option>
                    <flux:select.option value="high">Alto: lee series, lecturas y sellos (recomendado)</flux:select.option>
                    <flux:select.option value="auto">Automático: lo decide el modelo</flux:select.option>
                </flux:select>
                <flux:text class="mt-1 text-xs">Con detalle alto cada foto cuesta más, pero es el que alcanza a leer los números.</flux:text>
            </div>
        </div>

        <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:switch wire:model="revision_automatica" label="Revisar automáticamente al sincronizar" align="left"
                description="Cada registro que llegue con fotos nuevas se revisa solo, en segundo plano, y la sugerencia queda guardada en su detalle. Cada revisión se cobra. Los registros que ya estaban cargados no se revisan solos: se lanzan por tandas con el comando survey123:revisar-ia." />
        </div>

        <div>
            <flux:textarea wire:model="instrucciones" label="Instrucciones para la revisión" rows="22" class="font-mono text-xs" />
            <flux:text class="mt-1 text-xs">
                Aquí van las cosas específicas que debe buscar. Junto con estas instrucciones el modelo recibe las fotos,
                el campo en que se subió cada una y los datos que reportó el técnico (OT, contrato, serie, lectura y método).
                Responde siempre con el mismo formato, así que no hace falta pedirlo.
            </flux:text>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <flux:button type="submit" variant="primary" icon="check">Guardar</flux:button>
            <flux:button type="button" wire:click="restablecerInstrucciones" wire:confirm="¿Volver al texto recomendado? Se pierde lo que hayas escrito arriba hasta que guardes." icon="arrow-uturn-left">
                Restablecer instrucciones
            </flux:button>
        </div>
    </form>

    <flux:separator />

    <div class="space-y-4">
        <div>
            <flux:heading>Probar con un registro</flux:heading>
            <flux:text class="mt-1 text-sm">
                Usa lo que hay arriba, aunque no esté guardado. Se envían las fotos del registro elegido al proveedor de IA.
            </flux:text>
        </div>

        <div class="flex flex-wrap items-end gap-2">
            <div class="min-w-64 flex-1">
                <flux:select wire:model="registroPrueba" label="Registro" placeholder="Elige un registro con fotos">
                    @foreach ($registros as $id => $etiqueta)
                        <flux:select.option value="{{ $id }}">{{ $etiqueta }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
            <flux:button wire:click="probar" variant="filled" icon="sparkles" wire:loading.attr="disabled" wire:target="probar">
                <span wire:loading.remove wire:target="probar">Probar revisión</span>
                <span wire:loading wire:target="probar">Revisando…</span>
            </flux:button>
        </div>

        @if ($errorPrueba)
            <flux:callout variant="danger" icon="exclamation-triangle" :heading="$errorPrueba" />
        @endif

        @if ($resultadoPrueba)
            @include('livewire.partials.veredicto-fotos-ia', ['revision' => $resultadoPrueba])
        @endif
    </div>
</div>
