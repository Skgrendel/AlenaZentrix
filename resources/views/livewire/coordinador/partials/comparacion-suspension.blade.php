@php
    $resultadoEnum = $resultado ? \App\Enums\ResultadoComparacion::tryFrom($resultado) : null;
    $colores = [
        'success' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
        'danger' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
        'warning' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
    ];
    $estados = [
        'coincide' => ['Coincide', $colores['success']],
        'no_coincide' => ['No coincide', $colores['danger']],
        'sin_dato' => ['Sin dato', $colores['warning']],
    ];
@endphp

<div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
    <div class="mb-4 flex items-center justify-between gap-3">
        <h3 class="text-base font-semibold text-zinc-900 dark:text-white">Reportado por el técnico vs encontrado por el auditor</h3>
        @if ($resultadoEnum)
            <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $colores[$resultadoEnum->color()] }}">
                {{ $resultadoEnum->label() }}
            </span>
        @endif
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="text-xs uppercase text-zinc-400">
                <tr>
                    <th class="py-2 pr-4 font-medium">Campo</th>
                    <th class="py-2 pr-4 font-medium">Técnico (Survey123)</th>
                    <th class="py-2 pr-4 font-medium">Auditor</th>
                    <th class="py-2 font-medium">Validar</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @foreach ($comparacion['filas'] as $fila)
                    @php [$texto, $clase] = $estados[$fila['resultado']] ?? ['—', '']; @endphp
                    <tr>
                        <td class="py-2 pr-4 font-medium text-zinc-900 dark:text-white">
                            {{ $fila['etiqueta'] }}
                            @if ($fila['critico'])
                                <span class="ml-1 text-xs font-normal text-zinc-400">(clave)</span>
                            @endif
                        </td>
                        <td class="py-2 pr-4 text-zinc-700 dark:text-zinc-300">{{ $fila['reportado'] ?? '—' }}</td>
                        <td class="py-2 pr-4 text-zinc-700 dark:text-zinc-300">{{ $fila['encontrado'] ?? '—' }}</td>
                        <td class="py-2">
                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $clase }}">{{ $texto }}</span>
                            @if ($fila['detalle'] ?? null)
                                <span class="ml-2 text-xs text-zinc-500">{{ $fila['detalle'] }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
