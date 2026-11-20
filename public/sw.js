/*
 * Service worker de Alena Zentrix.
 *
 * Es deliberadamente conservador: la aplicación maneja datos privados de clientes, así que NO guarda
 * páginas ni respuestas de la API. Solo hace dos cosas:
 *   1. Si no hay conexión al abrir una página, muestra una pantalla de "sin conexión" con un botón para reintentar.
 *   2. Guarda en caché los archivos compilados (CSS/JS con huella en el nombre) para que la app cargue más rápido.
 *
 * Al cambiar este archivo, sube VERSION para que los dispositivos descarten la caché anterior.
 */
const VERSION = 'v1';
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
