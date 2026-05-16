<x-layouts::app :title="__('Dashboard')">
    <div class="flex flex-col gap-6">

       {{-- Contenedor Principal en 2 Columnas --}}
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-zinc-200 dark:border-zinc-800 pb-4">

    {{-- Columna Izquierda: Saludo y Fecha --}}
    <div>
        <h1 class="text-xl font-semibold text-zinc-800 dark:text-zinc-100">
            Bienvenido, {{ Auth::user()->fullName() }}
        </h1>
       <p class="text-sm text-zinc-500 dark:text-zinc-400 capitalize">
    Rol Asignado: {{ Auth::user()->getRoleNames()->first() ?? 'Sin Rol Asignado' }}
</p>
        <p class="text-sm text-zinc-500 dark:text-zinc-400 capitalize">
            {{ now()->isoFormat('dddd, D [de] MMMM [de] YYYY') }}
        </p>
         <p class="text-sm text-zinc-500 dark:text-zinc-400 capitalize">
           Empresa: {{ Auth::user()->company->nombre ?? 'Sin Empresa' }}
        </p>
    </div>

    {{-- Columna Derecha: Logo de la Empresa --}}
    <div class="flex items-center">
        @if(Auth::user()->company && Auth::user()->company->logo)
            <img
                src="{!! Auth::user()->company->logo !!}"
                alt="Logo de {{ Auth::user()->company->nombre }}"
                class="h-12 w-auto object-contain rounded-lg"
            />
        @else
            {{-- Nombre de la empresa en texto si no hay logo --}}
            <span class="text-lg font-bold text-zinc-700 dark:text-zinc-300">
                {{ Auth::user()->company->nombre ?? 'Sin Empresa' }}
            </span>
        @endif
    </div>

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
