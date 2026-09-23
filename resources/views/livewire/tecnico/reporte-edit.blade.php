
<div class="space-y-6">
@if ($turnosPendientes)
    {{-- Varios turnos de Vigilancia requieren corrección: el técnico elige cuál editar --}}
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Turnos por Corregir</flux:heading>
            <flux:text class="mt-1 text-sm">
                Orden: <span class="font-semibold">{{ $orden->numero_orden }}</span>
                <flux:badge color="red" class="ml-2">{{ $turnosPendientes->count() }} turno(s) pendiente(s)</flux:badge>
            </flux:text>
        </div>
        <flux:button href="{{ route('tecnico.mis-ordenes') }}" icon="arrow-left" variant="ghost" wire:navigate>
            Volver
        </flux:button>
    </div>

    <div class="divide-y divide-zinc-100 rounded-lg border border-zinc-200 bg-white dark:divide-zinc-800 dark:border-zinc-700 dark:bg-zinc-900">
        @foreach ($turnosPendientes as $turno)
            <a href="{{ route('tecnico.ordenes.reporte.editar', $orden) }}?reporte={{ $turno->id }}" wire:navigate
                class="flex items-center justify-between gap-4 p-4 transition hover:bg-zinc-50 dark:hover:bg-zinc-800">
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-zinc-900 dark:text-white">
                        Turno del
                        {{ ($turno->datos_servicio['fecha_turno'] ?? null) ? \Illuminate\Support\Carbon::parse($turno->datos_servicio['fecha_turno'])->format('d/m/Y') : $turno->created_at->format('d/m/Y') }}
                    </p>
                    @if ($turno->comentarios)
                        <p class="mt-1 truncate text-xs text-red-600 dark:text-red-400">{{ $turno->comentarios }}</p>
                    @endif
                </div>
                <flux:icon name="chevron-right" class="h-5 w-5 shrink-0 text-zinc-400" />
            </a>
        @endforeach
    </div>
@else
<div
    x-data="{
        draftKey: 'rpt_edit_{{ $reporte->id }}',
        hasDraft: false,
        savedAt: null,
        saveTimer: null,

        init() {
            const raw = localStorage.getItem(this.draftKey);
            if (raw) {
                try {
                    const parsed = JSON.parse(raw);
                    if (parsed.data && Object.keys(parsed.data).length > 0) {
                        this.hasDraft = true;
                        this.savedAt = parsed.savedAt
                            ? new Date(parsed.savedAt).toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit' })
                            : null;
                    }
                } catch(e) {
                    localStorage.removeItem(this.draftKey);
                }
            }

            this.$el.addEventListener('input', () => this.debouncedSave());
            this.$el.addEventListener('change', () => this.debouncedSave());
            setInterval(() => this.saveDraft(), 30000);
        },

        debouncedSave() {
            clearTimeout(this.saveTimer);
            this.saveTimer = setTimeout(() => this.saveDraft(), 2000);
        },

        // Campos de FileUpload: nunca deben guardarse/restaurarse desde el borrador.
        // Mientras un archivo se está subiendo, $wire.data contiene una referencia
        // temporal de Livewire (formato 'livewire-file:...'); si esa referencia se
        // persiste en localStorage y luego se restaura, queda un enlace roto porque
        // el archivo real nunca se subió al disco S3.
        fileFields: ['imagenes', 'foto_detector_fuga'],

        saveDraft() {
            const data = $wire.data;
            if (!data || Object.keys(data).length === 0) return;
            const safeData = { ...data };
            this.fileFields.forEach(key => delete safeData[key]);
            localStorage.setItem(this.draftKey, JSON.stringify({
                data: safeData,
                savedAt: new Date().toISOString()
            }));
        },

        restoreDraft() {
            const raw = localStorage.getItem(this.draftKey);
            if (!raw) return;
            try {
                const { data } = JSON.parse(raw);
                this.fileFields.forEach(key => delete data[key]);
                $wire.set('data', { ...$wire.data, ...data });
                this.hasDraft = false;
            } catch(e) {
                this.discardDraft();
            }
        },

        discardDraft() {
            localStorage.removeItem(this.draftKey);
            this.hasDraft = false;
        }
    }"
    @borrador-limpiar.window="discardDraft()">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Editar Reporte</flux:heading>
            <flux:text class="mt-1 text-sm">
                Orden: <span class="font-semibold">{{ $orden->numero_orden }}</span>   @if ($orden->cliente)
                    —{{ $orden->cliente }}
                @endif
                @if ($orden->estado === \App\Enums\EstadoOrden::Rechazada)
                    <flux:badge color="red" class="ml-2">Rechazada</flux:badge>
                @else
                    <flux:badge color="amber" class="ml-2">Turno con Corrección Pendiente</flux:badge>
                @endif
            </flux:text>
        </div>
        <flux:button href="{{ route('tecnico.mis-ordenes') }}" icon="arrow-left" variant="ghost" wire:navigate>
            Volver
        </flux:button>
    </div>

    @if($reporte->comentarios)
    {{-- Motivo de rechazo --}}
    <div class="rounded-lg border border-red-200 bg-red-50 p-5 dark:border-red-800 dark:bg-red-900/20">
        <h3 class="mb-2 text-sm font-semibold uppercase tracking-wide text-red-600 dark:text-red-400">Motivo de Rechazo</h3>
        <p class="text-sm text-red-700 dark:text-red-300">{{ $reporte->comentarios }}</p>
    </div>
    @endif

    {{-- Banner borrador --}}
    <div x-show="hasDraft" x-transition style="display:none"
        class="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-700 dark:bg-amber-900/20">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/40">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-amber-800 dark:text-amber-200">Borrador guardado</p>
                    <p class="text-xs text-amber-600 dark:text-amber-400"
                        x-text="savedAt ? 'Último guardado a las ' + savedAt + ' — tus datos están seguros' : 'Tienes datos sin enviar guardados en este dispositivo'">
                    </p>
                </div>
            </div>
            <div class="flex gap-2">
                <button type="button" @click="restoreDraft()"
                    class="rounded-lg bg-amber-600 px-4 py-2 text-xs font-semibold text-white transition hover:bg-amber-700">
                    Restaurar borrador
                </button>
                <button type="button" @click="discardDraft()"
                    class="rounded-lg border border-amber-300 px-4 py-2 text-xs font-semibold text-amber-700 transition hover:bg-amber-100 dark:border-amber-600 dark:text-amber-400 dark:hover:bg-amber-900/30">
                    Descartar
                </button>
            </div>
        </div>
    </div>

    {{-- Datos del punto de servicio (solo lectura) --}}
    @if ($orden->direccion && $orden->barrio && $orden->contrato && $orden->medidor && $orden->cliente)
        <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Punto de
                Servicio</h3>
            <dl class="grid grid-cols-2 gap-x-6 gap-y-2 sm:grid-cols-3">
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Cliente</dt>
                    <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $orden->cliente }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Contrato</dt>
                    <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $orden->contrato }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Medidor</dt>
                    <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $orden->medidor }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Tipo de Medidor</dt>
                    <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $orden->tipo_medidor }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-zinc-400">Dirección</dt>
                    <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-white">{{ $orden->direccion }},
                        {{ $orden->barrio }}</dd>
                </div>
            </dl>
        </div>
    @endif
    @if ($orden->antecedente)
        <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Antecedentes
            </h3>
            <p class="text-sm text-zinc-900 dark:text-white">{{ $orden->antecedente }}</p>
        </div>
    @endif
    @if ($orden->objetivo)
        <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Objetivo
            </h3>
            <p class="text-sm text-zinc-900 dark:text-white">{{ $orden->objetivo }}</p>
        </div>
    @endif
    {{-- @if (!empty($orden->actividades))
        <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Actividades
            </h3>
            <ul class="list-disc space-y-1 pl-5">
                @foreach ($orden->actividades as $actividad)
                    <li class="text-sm text-zinc-900 dark:text-white">{{ $actividad['descripcion'] ?? '' }}</li>
                @endforeach
            </ul>
        </div>
    @endif --}}

    @if (!empty($imagenesExistentesUrls))
        {{-- Fotos ya guardadas: galería de solo lectura, separada del campo de subida
             de fotos nuevas para que quede claro que esto ya está en el reporte y no
             hace falta volver a adjuntarlo. --}}
        <div x-data="{ open: false, src: '' }" @keydown.escape.window="open = false"
            class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <h3 class="mb-1 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                Fotos ya Guardadas ({{ count($imagenesExistentesUrls) }})
            </h3>
            <p class="mb-4 text-xs text-zinc-400 dark:text-zinc-500">
                Estas fotos ya quedaron registradas en el reporte. Usa el campo de "Imágenes" del formulario solo si
                necesitas agregar fotos adicionales.
            </p>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                @foreach ($imagenesExistentesUrls as $url)
                    <button type="button" @click="src = '{{ $url }}'; open = true"
                        class="group relative block overflow-hidden rounded-xl border border-zinc-200 shadow-sm transition hover:shadow-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-zinc-700">
                        <img src="{{ $url }}" alt="Foto ya guardada"
                            class="h-32 w-full object-cover transition duration-300 group-hover:scale-105">
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
    @endif

    {{-- GPS silencioso: captura en segundo plano, modal si está bloqueado --}}
    <div x-data="{
        modalAbierto: false,
        captureLocation() {
            if (!navigator.geolocation) {
                this.modalAbierto = true;
                return;
            }
            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    $wire.set('latitud', String(pos.coords.latitude));
                    $wire.set('longitud', String(pos.coords.longitude));
                },
                () => { this.modalAbierto = true; }, { enableHighAccuracy: true, timeout: 15000 }
            );
        }
    }" x-init="captureLocation()">
        {{-- Modal permiso de ubicación --}}
        <div x-show="modalAbierto" x-transition.opacity
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" style="display: none">
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-2xl dark:bg-zinc-900">
                <div class="mb-4 flex items-center gap-3">
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/40">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-amber-600" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-semibold text-zinc-900 dark:text-white">Permiso de ubicación requerido
                        </h3>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">Se necesita para registrar el punto de
                            atención</p>
                    </div>
                </div>
                <p class="mb-5 text-sm text-zinc-600 dark:text-zinc-300">
                    Para enviar el reporte es necesario que actives la ubicación en tu navegador. Haz clic en el ícono
                    de candado en la barra de dirección y permite el acceso a la ubicación, luego intenta de nuevo.
                </p>
                <div class="flex justify-end gap-3">
                    <flux:button variant="ghost" @click="modalAbierto = false">Cancelar</flux:button>
                    <flux:button variant="primary" @click="modalAbierto = false; captureLocation()">Reintentar
                    </flux:button>
                </div>
            </div>
        </div>
    </div>

    {{-- Formulario --}}
    <form wire:submit="submit" class="space-y-6">
        <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            {{ $this->form }}
        </div>

        <div class="flex justify-end gap-3">
            <flux:button href="{{ route('tecnico.mis-ordenes') }}" variant="ghost" wire:navigate>
                Cancelar
            </flux:button>
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                <span wire:loading.remove>Reenviar Reporte</span>
                <span wire:loading>Enviando...</span>
            </flux:button>
        </div>
    </form>
</div>
@endif
</div>
