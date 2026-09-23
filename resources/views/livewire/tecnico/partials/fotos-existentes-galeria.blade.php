{{-- Galería de solo lectura para las fotos ya guardadas de un reporte. Se usa dentro de
     un Placeholder de Filament (ver ReporteEdit::seccionFotosExistentes), separada del
     campo de subida de fotos nuevas. --}}
<div x-data="{ open: false, src: '' }" @keydown.escape.window="open = false">
    <div class="grid grid-cols-4 gap-2 sm:grid-cols-6 md:grid-cols-8">
        @foreach ($urls as $url)
            <button type="button" @click="src = '{{ $url }}'; open = true"
                class="group relative block aspect-square overflow-hidden rounded-lg border border-zinc-200 shadow-sm transition hover:shadow-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-zinc-700">
                <img src="{{ $url }}" alt="Foto ya guardada"
                    class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
            </button>
        @endforeach
    </div>

    {{-- Lightbox --}}
    <div x-show="open" x-transition.opacity @click.self="open = false"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4" style="display: none">
        <div class="relative max-h-full max-w-4xl">
            <button type="button" @click="open = false"
                class="absolute -right-3 -top-3 flex h-8 w-8 items-center justify-center rounded-full bg-white text-zinc-800 shadow-lg hover:bg-zinc-100">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
            <img :src="src" alt="Foto ampliada" class="max-h-[85vh] max-w-full rounded-lg object-contain shadow-2xl">
        </div>
    </div>
</div>
