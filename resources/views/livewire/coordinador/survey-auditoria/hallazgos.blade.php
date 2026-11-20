@php($hallazgos = collect($registro->hallazgos ?? []))

<div class="space-y-3">
    @if ($registro->auditado_en === null)
        <flux:text>Este registro todavía no se ha auditado.</flux:text>
    @elseif ($hallazgos->isEmpty())
        <flux:text>Sin hallazgos: el registro cumple todas las reglas.</flux:text>
    @else
        <ul class="space-y-2">
            @foreach ($hallazgos->sortBy(fn ($h) => $h['severidad'] === 'error' ? 0 : 1) as $hallazgo)
                <li class="flex gap-2 text-sm">
                    <span class="{{ $hallazgo['severidad'] === 'error' ? 'text-red-600' : 'text-amber-600' }} font-semibold">
                        {{ $hallazgo['severidad'] === 'error' ? 'Error' : 'Alerta' }}
                    </span>
                    <span>{{ $hallazgo['mensaje'] }}</span>
                </li>
            @endforeach
        </ul>
    @endif
</div>
