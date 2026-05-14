<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    {{-- wire:ignore en el wrapper externo evita que Livewire reinicialice Alpine al hacer morph --}}
    <div
        wire:ignore
        x-data="{
            state: $wire.entangle('{{ $getStatePath() }}'),
            pad: null,
            observer: null,
            init() {
                const canvas = this.$refs.canvas;
                this.pad = new SignaturePad(canvas, { backgroundColor: 'rgba(0,0,0,0)' });

                const setSize = () => {
                    const ratio = Math.max(window.devicePixelRatio || 1, 1);
                    const w = canvas.offsetWidth;
                    const h = canvas.offsetHeight;
                    if (!w || !h) return;
                    if (canvas.width === w * ratio && canvas.height === h * ratio) return;
                    canvas.width = w * ratio;
                    canvas.height = h * ratio;
                    canvas.getContext('2d').scale(ratio, ratio);
                    this.pad.clear();
                    this.state = null;
                };

                setSize();
                setTimeout(setSize, 100);

                this.observer = new ResizeObserver(setSize);
                this.observer.observe(canvas.parentElement);

                this.pad.addEventListener('endStroke', () => {
                    this.state = this.pad.isEmpty() ? null : this.pad.toDataURL('image/png');
                });
            },
            clear() {
                this.pad.clear();
                this.state = null;
            },
            destroy() {
                if (this.observer) this.observer.disconnect();
            }
        }"
    >
        <div class="relative rounded-lg border-2 border-zinc-300 bg-white dark:border-zinc-600" style="height: 200px;">
            {{-- Línea guía CSS: independiente del canvas, nunca se borra --}}
            <div
                class="pointer-events-none absolute left-4 right-4"
                style="top: 68%; border-top: 1px dashed #94a3b8;"
            ></div>
            <canvas x-ref="canvas" class="absolute inset-0 h-full w-full touch-none rounded-lg"></canvas>
            <div class="absolute bottom-2 right-2 z-10">
                <button
                    type="button"
                    @click="clear()"
                    class="rounded bg-zinc-100 px-2 py-1 text-xs text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-700 dark:text-zinc-300"
                >
                    Limpiar
                </button>
            </div>
            <div class="pointer-events-none absolute select-none text-xs text-zinc-400" style="top: 72%; left: 16px;">
                Firme aquí
            </div>
        </div>
    </div>
</x-dynamic-component>
