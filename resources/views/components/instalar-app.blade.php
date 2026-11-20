{{--
    Botón "Instalar app" del menú lateral.
    - Android y computador (Chrome, Edge): usa el aviso que guarda el encabezado y abre el cuadro de instalación.
    - iPhone y iPad (Safari): no existe ese cuadro, así que muestra los pasos para añadirla a la pantalla de inicio.
    - Se oculta si la app ya está instalada o si el navegador no la puede instalar.
--}}
<div
    x-data="{
        aviso: window.__avisoInstalacion ?? null,
        instalada: window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true,
        esIos: /iphone|ipad|ipod/i.test(navigator.userAgent) && ! window.MSStream,
        ayuda: false,
        get visible() { return ! this.instalada && (this.aviso !== null || this.esIos); },
        async instalar() {
            if (this.aviso === null) {
                this.ayuda = ! this.ayuda;

                return;
            }

            this.aviso.prompt();
            await this.aviso.userChoice;
            window.__avisoInstalacion = null;
            this.aviso = null;
        },
    }"
    @app-instalable.window="aviso = window.__avisoInstalacion"
    @app-instalada.window="instalada = true"
    x-show="visible"
    style="display: none"
    data-test="instalar-app"
>
    <flux:sidebar.item icon="arrow-down-tray" href="#" x-on:click.prevent="instalar()">
        {{ __('Instalar app') }}
    </flux:sidebar.item>

    <div x-show="ayuda" style="display: none" class="mx-2 mb-2 rounded-lg bg-zinc-100 p-3 text-xs leading-relaxed text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
        {{ __('En Safari, toca el botón Compartir y luego «Añadir a pantalla de inicio».') }}
    </div>
</div>
