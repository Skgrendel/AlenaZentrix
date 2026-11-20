<div class="space-y-6">
    <div>
        <flux:heading size="lg">Revisión de fotos con IA</flux:heading>
        <flux:text class="mt-1 text-sm">
            Elige el modelo y escribe las instrucciones con las que se revisan las fotos de las suspensiones.
            La IA solo sugiere: no cambia el resultado de la auditoría y una persona decide.
        </flux:text>
    </div>

    <form wire:submit="guardar" class="space-y-5">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <flux:input wire:model.live.debounce.400ms="modelo" label="Modelo" list="modelos-ia" placeholder="gpt-4o" />
                <datalist id="modelos-ia">
                    @foreach ($modelos as $m)
                        <option value="{{ $m }}"></option>
                    @endforeach
                </datalist>
                <flux:text class="mt-1 text-xs">
                    @if ($tarifa)
                        Tarifa de referencia: US$ {{ number_format($tarifa['entrada'], 2) }} por millón de tokens de entrada y
                        US$ {{ number_format($tarifa['salida'], 2) }} de salida. Confírmala en la tarifa vigente del proveedor.
                    @else
                        Este modelo no tiene tarifa registrada: la prueba no mostrará costo.
                    @endif
                </flux:text>
            </div>

            <flux:select wire:model="detalle_imagen" label="Detalle con que se miran las fotos">
                <flux:select.option value="low">Bajo: más barato, no alcanza a leer series ni lecturas</flux:select.option>
                <flux:select.option value="high">Alto: lee series, lecturas y sellos (recomendado)</flux:select.option>
                <flux:select.option value="auto">Automático: lo decide el modelo</flux:select.option>
            </flux:select>
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
            @php($v = $resultadoPrueba['veredicto'])
            <div class="space-y-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:text class="text-sm">{{ $v['resumen'] }}</flux:text>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="text-xs text-zinc-500">
                            <tr>
                                <th class="py-1 pr-3">#</th>
                                <th class="py-1 pr-3">Campo</th>
                                <th class="py-1 pr-3">Muestra</th>
                                <th class="py-1 pr-3">Coincide</th>
                                <th class="py-1 pr-3">Calidad</th>
                                <th class="py-1">Observación</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($v['fotos'] as $foto)
                                <tr class="border-t border-zinc-100 dark:border-zinc-800">
                                    <td class="py-1 pr-3">{{ $foto['numero'] }}</td>
                                    <td class="py-1 pr-3">{{ $foto['campo_declarado'] }}</td>
                                    <td class="py-1 pr-3">{{ $foto['muestra'] }}</td>
                                    <td class="py-1 pr-3 {{ $foto['coincide_con_campo'] ? '' : 'font-semibold text-red-600' }}">{{ $foto['coincide_con_campo'] ? 'Sí' : 'No' }}</td>
                                    <td class="py-1 pr-3">{{ $foto['calidad'] }}</td>
                                    <td class="py-1">{{ $foto['observacion'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @php($d = $v['datos_leidos'])
                @php($si = fn ($x) => $x === null ? '—' : ($x ? 'coincide' : 'NO coincide'))
                <div class="grid gap-2 text-sm sm:grid-cols-3">
                    <div><span class="text-zinc-500">Contrato leído:</span> {{ $d['contrato'] ?? '—' }} <span class="text-xs">({{ $si($d['contrato_coincide']) }})</span></div>
                    <div><span class="text-zinc-500">Serie leída:</span> {{ $d['serie_medidor'] ?? '—' }} <span class="text-xs">({{ $si($d['serie_coincide']) }})</span></div>
                    <div><span class="text-zinc-500">Lectura leída:</span> {{ $d['lectura_medidor'] ?? '—' }} <span class="text-xs">({{ $si($d['lectura_coincide']) }})</span></div>
                </div>

                <div class="text-sm">
                    Fotos repetidas: <strong>{{ $v['fotos_repetidas'] ? 'sí' : 'no' }}</strong> ·
                    Suspensión visible: <strong>{{ $v['suspension_visible'] === null ? 'no se sabe' : ($v['suspension_visible'] ? 'sí' : 'no') }}</strong>
                </div>

                @if ($v['hallazgos'])
                    <ul class="space-y-1 text-sm">
                        @foreach ($v['hallazgos'] as $h)
                            <li class="flex gap-2">
                                <span class="font-semibold {{ $h['severidad'] === 'error' ? 'text-red-600' : ($h['severidad'] === 'alerta' ? 'text-amber-600' : 'text-zinc-500') }}">{{ ucfirst($h['severidad']) }}</span>
                                <span>{{ $h['detalle'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <flux:text class="text-xs">
                    {{ $resultadoPrueba['modelo'] }} · {{ $resultadoPrueba['fotos'] }} fotos ·
                    {{ number_format($resultadoPrueba['tokens_entrada']) }} tokens de entrada, {{ number_format($resultadoPrueba['tokens_salida']) }} de salida ·
                    {{ $resultadoPrueba['costo_usd'] === null ? 'sin tarifa para estimar el costo' : 'costo estimado US$ '.number_format($resultadoPrueba['costo_usd'], 4) }}
                </flux:text>
            </div>
        @endif
    </div>
</div>
