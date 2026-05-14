<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div
        x-data="{
            state: $wire.entangle('{{ $getStatePath() }}').defer,
            pad: null,
            init() {
                this.pad = new SignaturePad(this.$refs.canvas, { backgroundColor: 'rgb(255,255,255)' });
                const ratio = Math.max(window.devicePixelRatio || 1, 1);
                const canvas = this.$refs.canvas;
                canvas.width = canvas.offsetWidth * ratio;
                canvas.height = canvas.offsetHeight * ratio;
                canvas.getContext('2d').scale(ratio, ratio);
                this.pad.addEventListener('endStroke', () => {
                    this.state = this.pad.isEmpty() ? null : this.pad.toDataURL('image/png');
                });
            },
            clear() { this.pad.clear(); this.state = null; }
        }"
    >
        <div class="relative rounded-lg border-2 border-zinc-300 bg-white dark:border-zinc-600" style="height: 200px;">
            <canvas x-ref="canvas" class="h-full w-full touch-none rounded-lg"></canvas>
            <div class="absolute bottom-2 right-2">
                <button
                    type="button"
                    @click="clear()"
                    class="rounded bg-zinc-100 px-2 py-1 text-xs text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-700 dark:text-zinc-300"
                >
                    Limpiar
                </button>
            </div>
        </div>
    </div>
</x-dynamic-component>
