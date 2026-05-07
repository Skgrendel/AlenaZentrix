<x-layouts::app :title="__('Dashboard')">
    <div class="flex flex-col gap-6">

        {{-- Saludo --}}
        <div>
            <h1 class="text-xl font-semibold text-zinc-800 dark:text-zinc-100">
                Bienvenido, {{ Auth::user()->fullName() }}
            </h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                {{ now()->isoFormat('dddd, D [de] MMMM [de] YYYY') }}
            </p>
        </div>

        {{-- Stats Overview --}}
        @livewire(\App\Filament\Widgets\OrdenesStatsOverview::class)

        {{-- Charts --}}
        @can('ordenes.ver')
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="lg:col-span-2">
                    @livewire(\App\Filament\Widgets\OrdenesPorEstadoChart::class)
                </div>
                <div class="lg:col-span-1">
                    @livewire(\App\Filament\Widgets\OrdenesPorServicioChart::class)
                </div>
            </div>
        @else
            @livewire(\App\Filament\Widgets\OrdenesPorEstadoChart::class)
        @endcan

    </div>
</x-layouts::app>
