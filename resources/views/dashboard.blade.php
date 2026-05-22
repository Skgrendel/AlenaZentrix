<x-layouts::app :title="__('Panel de Control Operativo')">
    <div class="flex flex-col gap-6">

        {{-- Header --}}
        <div class="flex flex-col gap-3 border-b border-zinc-200 pb-4 dark:border-zinc-800 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-semibold text-zinc-800 dark:text-zinc-100">
                    Bienvenido, {{ Auth::user()->fullName() }}
                </h1>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    Rol Asignado: {{ Auth::user()->getRoleNames()->first() ?? 'Sin Rol Asignado' }}
                </p>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    Empresa: {{ Auth::user()->company->nombre ?? 'Sin Empresa' }}
                </p>
                <p class="text-sm capitalize text-zinc-500 dark:text-zinc-400">
                    {{ now()->isoFormat('dddd, D [de] MMMM [de] YYYY') }}
                </p>
            </div>
            <div class="flex items-center">
                @if (Auth::user()->company?->logo)
                    <img
                        src="{!! Auth::user()->company->logo !!}"
                        alt="Logo de {{ Auth::user()->company->nombre }}"
                        class="h-12 w-auto rounded-lg object-contain"
                    />
                @else
                    <span class="text-lg font-bold text-zinc-700 dark:text-zinc-300">
                        {{ Auth::user()->company->nombre ?? '' }}
                    </span>
                @endif
            </div>
        </div>

        {{-- KPIs --}}
        @livewire(\App\Filament\Widgets\OrdenesStatsOverview::class)

        @can('ordenes.ver')

            {{-- Tendencia mensual + distribución por tipo de servicio --}}
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="lg:col-span-2">
                    @livewire(\App\Filament\Widgets\TendenciaMensualChart::class)
                </div>
                <div class="lg:col-span-1">
                    @livewire(\App\Filament\Widgets\OrdenesPorServicioChart::class)
                </div>
            </div>

            {{-- Rendimiento por investigador --}}
            @livewire(\App\Filament\Widgets\RendimientoInvestigadoresWidget::class)

            {{-- Distribución actual por estado --}}
            @livewire(\App\Filament\Widgets\OrdenesPorEstadoChart::class)

        @else

            @livewire(\App\Filament\Widgets\OrdenesPorEstadoChart::class)

        @endcan

    </div>
</x-layouts::app>
