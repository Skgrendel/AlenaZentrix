<div wire:poll.5s="checkZipNotificacion">
    @if($zipListo)
        <div class="mb-4 flex items-center justify-between rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-blue-800 dark:border-blue-700 dark:bg-blue-900/30 dark:text-blue-300">
            <span>{{ $zipMensaje }}</span>
            <a href="{{ $zipUrl }}" class="ml-4 font-semibold underline">Descargar ZIP</a>
        </div>
    @endif

    {{ $this->table }}
</div>
