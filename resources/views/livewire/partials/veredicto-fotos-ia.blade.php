@php($v = $revision['veredicto'])
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
        {{ $revision['modelo'] }} · {{ $revision['fotos'] }} fotos ·
        {{ number_format($revision['tokens_entrada']) }} tokens de entrada, {{ number_format($revision['tokens_salida']) }} de salida ·
        {{ $revision['costo_usd'] === null ? 'sin tarifa para estimar el costo' : 'costo estimado US$ '.number_format($revision['costo_usd'], 4) }}
    </flux:text>
</div>
