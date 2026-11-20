/*
 * Service worker de Alena Zentrix.
 *
 * Es deliberadamente conservador: la aplicación maneja datos privados de clientes, así que NO guarda
 * páginas ni respuestas de la API. Solo hace tres cosas:
 *   1. Si no hay conexión al abrir una página, muestra una pantalla de "sin conexión" con un botón para reintentar.
 *   2. Guarda en caché los archivos compilados (CSS/JS con huella en el nombre) para que la app cargue más rápido.
 *   3. Muestra los avisos push (orden de trabajo asignada) y abre la orden al tocarlos. El aviso solo trae el número de la OT.
 *
 * Al cambiar este archivo, sube VERSION para que los dispositivos descarten la caché anterior.
 */
const VERSION = 'v2';
const CACHE = `alena-${VERSION}`;
const PAGINA_SIN_CONEXION = '/offline.html';
const PRECARGA = [PAGINA_SIN_CONEXION, '/icons/icon-192.png', '/icons/icon-512.png'];

self.addEventListener('install', (evento) => {
    evento.waitUntil(
        caches.open(CACHE)
            .then((cache) => cache.addAll(PRECARGA))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (evento) => {
    evento.waitUntil(
        caches.keys()
            .then((nombres) => Promise.all(
                nombres.filter((nombre) => nombre.startsWith('alena-') && nombre !== CACHE).map((nombre) => caches.delete(nombre)),
            ))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (evento) => {
    const peticion = evento.request;

    // Solo lecturas del mismo sitio; los envíos de formularios y Livewire (POST) pasan directo a la red.
    if (peticion.method !== 'GET') {
        return;
    }

    const url = new URL(peticion.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    // Páginas: siempre desde la red (nunca se guardan). Sin conexión, se muestra la pantalla de aviso.
    if (peticion.mode === 'navigate') {
        evento.respondWith(fetch(peticion).catch(() => caches.match(PAGINA_SIN_CONEXION)));

        return;
    }

    // Archivos compilados: caché primero, porque su nombre cambia cuando cambia el contenido.
    if (url.pathname.startsWith('/build/assets/')) {
        evento.respondWith(
            caches.open(CACHE).then(async (cache) => {
                const guardado = await cache.match(peticion);

                if (guardado) {
                    return guardado;
                }

                const respuesta = await fetch(peticion);

                if (respuesta.ok) {
                    cache.put(peticion, respuesta.clone());
                }

                return respuesta;
            }),
        );
    }
});

// Avisos push: el servidor manda {title, body, icon, badge, tag, data: {url}}.
self.addEventListener('push', (evento) => {
    let datos = {};

    try {
        datos = evento.data ? evento.data.json() : {};
    } catch (error) {
        datos = { body: evento.data ? evento.data.text() : '' };
    }

    evento.waitUntil(
        self.registration.showNotification(datos.title || 'Alena Zentrix', {
            body: datos.body || '',
            icon: datos.icon || '/icons/icon-192.png',
            badge: datos.badge || '/icons/icon-192.png',
            tag: datos.tag,
            data: datos.data || {},
        }),
    );
});

// Al tocar el aviso se abre la orden: si la app ya está abierta se usa esa ventana; si no, se abre una nueva.
self.addEventListener('notificationclick', (evento) => {
    evento.notification.close();

    // Solo rutas de este mismo sitio.
    const destino = new URL((evento.notification.data && evento.notification.data.url) || '/tecnico/mis-ordenes', self.location.origin);

    if (destino.origin !== self.location.origin) {
        return;
    }

    evento.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((ventanas) => {
            for (const ventana of ventanas) {
                if ('focus' in ventana) {
                    ventana.navigate(destino.href);

                    return ventana.focus();
                }
            }

            return self.clients.openWindow(destino.href);
        }),
    );
});
