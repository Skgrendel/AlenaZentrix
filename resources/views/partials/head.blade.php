<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

{{-- Aplicación instalable (PWA) --}}
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#17222e">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Alena">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<script data-navigate-once>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {}));
    }

    // El navegador avisa una sola vez por carga completa que la app se puede instalar: se guarda el aviso
    // para que el botón "Instalar app" del menú lo use aunque se navegue entre páginas.
    window.addEventListener('beforeinstallprompt', (evento) => {
        evento.preventDefault();
        window.__avisoInstalacion = evento;
        window.dispatchEvent(new CustomEvent('app-instalable'));
    });
    window.addEventListener('appinstalled', () => {
        window.__avisoInstalacion = null;
        window.dispatchEvent(new CustomEvent('app-instalada'));
    });
</script>

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
@filamentStyles
@stack('styles')
