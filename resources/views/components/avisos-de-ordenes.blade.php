{{--
    Botón "Avisos de órdenes" del menú lateral: activa o desactiva los avisos al celular cuando se asigna una orden.
    - Solo se ve si el navegador admite avisos push y el servidor los tiene configurados.
    - iPhone: los avisos solo existen con la app instalada en la pantalla de inicio; en Safari normal el botón no aparece.
    - El navegador exige que el permiso se pida desde un toque del usuario, por eso es un botón.
--}}
@if (\App\Services\AvisoDeOrdenAsignada::disponible())
    <div
        x-data="{
            clave: @js(config('webpush.vapid.public_key')),
            rutaGuardar: @js(route('tecnico.avisos.guardar')),
            token: @js(csrf_token()),
            admitido: 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window,
            activo: false,
            bloqueado: false,
            ocupado: false,
            mensaje: '',
            async init() {
                if (! this.admitido) return;
                this.bloqueado = Notification.permission === 'denied';
                const registro = await navigator.serviceWorker.ready;
                this.activo = (await registro.pushManager.getSubscription()) !== null && Notification.permission === 'granted';
            },
            clavePublica() {
                const relleno = '='.repeat((4 - (this.clave.length % 4)) % 4);
                const base64 = (this.clave + relleno).replace(/-/g, '+').replace(/_/g, '/');
                return Uint8Array.from(atob(base64), (c) => c.charCodeAt(0));
            },
            async enviar(metodo, cuerpo) {
                const respuesta = await fetch(this.rutaGuardar, {
                    method: metodo,
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.token },
                    body: JSON.stringify(cuerpo),
                });
                if (! respuesta.ok) throw new Error('El servidor no guardó el aviso.');
            },
            async alternar() {
                if (this.ocupado) return;
                this.ocupado = true;
                this.mensaje = '';
                try {
                    const registro = await navigator.serviceWorker.ready;
                    const actual = await registro.pushManager.getSubscription();

                    if (this.activo && actual) {
                        await this.enviar('DELETE', { endpoint: actual.endpoint });
                        await actual.unsubscribe();
                        this.activo = false;
                        return;
                    }

                    const permiso = await Notification.requestPermission();
                    this.bloqueado = permiso === 'denied';
                    if (permiso !== 'granted') return;

                    const suscripcion = actual ?? await registro.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: this.clavePublica() });
                    const datos = suscripcion.toJSON();
                    await this.enviar('POST', { endpoint: datos.endpoint, keys: datos.keys, contentEncoding: (PushManager.supportedContentEncodings || ['aes128gcm'])[0] });
                    this.activo = true;
                } catch (error) {
                    this.mensaje = 'No se pudieron activar los avisos. Inténtalo de nuevo.';
                } finally {
                    this.ocupado = false;
                }
            },
        }"
        x-show="admitido"
        style="display: none"
        data-test="avisos-de-ordenes"
    >
        <flux:sidebar.item icon="bell" href="#" x-on:click.prevent="alternar()">
            <span x-text="activo ? '{{ __('Avisos de órdenes: activados') }}' : '{{ __('Activar avisos de órdenes') }}'"></span>
        </flux:sidebar.item>

        <div x-show="bloqueado" style="display: none" class="mx-2 mb-2 rounded-lg bg-zinc-100 p-3 text-xs leading-relaxed text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
            {{ __('Los avisos están bloqueados en este teléfono. Actívalos en los ajustes del navegador o de la app y vuelve a intentarlo.') }}
        </div>
        <div x-show="mensaje !== ''" style="display: none" x-text="mensaje" class="mx-2 mb-2 rounded-lg bg-red-50 p-3 text-xs text-red-700 dark:bg-red-900/30 dark:text-red-300"></div>
    </div>
@endif
