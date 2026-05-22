<div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">

    <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-gray-700">
        <div>
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                Rendimiento por Investigador
            </h3>
            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                Completadas del mes actual · Efectividad histórica acumulada
            </p>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 bg-gray-50/60 dark:border-gray-800 dark:bg-gray-800/40">
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Investigador
                    </th>
                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Total
                    </th>
                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        En Curso
                    </th>
                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Completadas (mes)
                    </th>
                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Efectividad
                    </th>
                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Prom. Días
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($this->getInvestigadores() as $inv)
                    <tr class="transition-colors hover:bg-gray-50/60 dark:hover:bg-gray-800/30">
                        <td class="px-6 py-3.5">
                            <div class="flex items-center gap-3">
                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary-100 dark:bg-primary-900/40">
                                    <span class="text-xs font-bold text-primary-700 dark:text-primary-300">
                                        {{ $inv->iniciales }}
                                    </span>
                                </div>
                                <div>
                                    <div class="font-medium text-gray-900 dark:text-white">{{ $inv->nombre_completo }}</div>
                                    @if ($inv->cargo)
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $inv->cargo }}</div>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3.5 text-center font-medium text-gray-700 dark:text-gray-300">
                            {{ $inv->total ?: '—' }}
                        </td>
                        <td class="px-4 py-3.5 text-center">
                            @if ($inv->activas > 0)
                                <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-900/30 dark:text-amber-400">
                                    {{ $inv->activas }}
                                </span>
                            @else
                                <span class="text-gray-400 dark:text-gray-600">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3.5 text-center">
                            <span @class([
                                'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold',
                                'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400' => $inv->completadas_mes > 0,
                                'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-500' => $inv->completadas_mes == 0,
                            ])>
                                {{ $inv->completadas_mes }}
                            </span>
                        </td>
                        <td class="px-4 py-3.5 text-center">
                            @if ($inv->efectividad !== null)
                                <span @class([
                                    'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold',
                                    'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400' => $inv->efectividad >= 80,
                                    'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400' => $inv->efectividad >= 60 && $inv->efectividad < 80,
                                    'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400' => $inv->efectividad < 60,
                                ])>
                                    {{ $inv->efectividad }}%
                                </span>
                            @else
                                <span class="text-xs text-gray-400 dark:text-gray-600">Sin datos</span>
                            @endif
                        </td>
                        <td class="px-4 py-3.5 text-center text-gray-600 dark:text-gray-400">
                            {{ $inv->promedio_dias !== null ? $inv->promedio_dias.' días' : '—' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                            No hay investigadores registrados en esta empresa.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>
