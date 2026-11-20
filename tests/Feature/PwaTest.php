<?php

use Illuminate\Support\Facades\Route;

/** @return array<string, mixed> */
function manifiesto(): array
{
    return json_decode(file_get_contents(public_path('manifest.json')), true, flags: JSON_THROW_ON_ERROR);
}

test('el manifiesto tiene lo necesario para poder instalar la aplicación', function () {
    $m = manifiesto();

    expect($m['name'])->toBe('Alena Zentrix')
        ->and($m['short_name'])->not->toBeEmpty()
        ->and($m['display'])->toBe('standalone')
        ->and($m['start_url'])->toStartWith('/')
        ->and($m['scope'])->toBe('/')
        ->and($m['theme_color'])->toMatch('/^#[0-9a-f]{6}$/i')
        ->and($m['background_color'])->toMatch('/^#[0-9a-f]{6}$/i')
        ->and($m['lang'])->toBe('es');
});

test('el manifiesto declara iconos de 192 y 512 y uno adaptable', function () {
    $iconos = collect(manifiesto()['icons']);

    expect($iconos->contains(fn ($i) => $i['sizes'] === '192x192' && ($i['purpose'] ?? 'any') === 'any'))->toBeTrue()
        ->and($iconos->contains(fn ($i) => $i['sizes'] === '512x512' && ($i['purpose'] ?? 'any') === 'any'))->toBeTrue()
        ->and($iconos->contains(fn ($i) => ($i['purpose'] ?? null) === 'maskable'))->toBeTrue();
});

test('cada icono del manifiesto existe y tiene las medidas que declara', function () {
    foreach (manifiesto()['icons'] as $icono) {
        $ruta = public_path(ltrim($icono['src'], '/'));
        expect(file_exists($ruta))->toBeTrue("Falta {$icono['src']}");

        [$ancho, $alto] = getimagesize($ruta);
        expect("{$ancho}x{$alto}")->toBe($icono['sizes'], "El icono {$icono['src']} no mide lo declarado");
    }
});

test('el icono para iOS mide 180 x 180', function () {
    [$ancho, $alto] = getimagesize(public_path('apple-touch-icon.png'));

    expect([$ancho, $alto])->toBe([180, 180]);
});

test('la página de inicio y los accesos directos del manifiesto apuntan a rutas que existen', function () {
    $m = manifiesto();
    $urls = [$m['start_url'], ...array_column($m['shortcuts'], 'url')];

    foreach ($urls as $url) {
        $existe = collect(Route::getRoutes()->getRoutes())->contains(fn ($ruta) => '/'.ltrim($ruta->uri(), '/') === $url && in_array('GET', $ruta->methods(), true));
        expect($existe)->toBeTrue("La ruta {$url} no existe");
    }
});

test('el service worker no guarda páginas ni peticiones que no sean lecturas', function () {
    $sw = file_get_contents(public_path('sw.js'));

    expect($sw)->toContain("peticion.method !== 'GET'")
        ->toContain("peticion.mode === 'navigate'")
        ->toContain("'/build/assets/'")
        ->toContain('const VERSION');

    // La única escritura en caché es la de los archivos compilados.
    expect(substr_count($sw, 'cache.put('))->toBe(1);
});

test('la pantalla sin conexión existe y está en español', function () {
    $html = file_get_contents(public_path('offline.html'));

    expect($html)->toContain('Sin conexión')->toContain('Reintentar')->toContain('borrador');
});

test('el service worker precarga solo archivos que existen', function () {
    preg_match('/const PAGINA_SIN_CONEXION = \'([^\']+)\'/', file_get_contents(public_path('sw.js')), $coincidencia);
    expect(file_exists(public_path(ltrim($coincidencia[1], '/'))))->toBeTrue();

    preg_match_all("/'(\/icons\/[^']+)'/", file_get_contents(public_path('sw.js')), $iconos);
    foreach ($iconos[1] as $icono) {
        expect(file_exists(public_path(ltrim($icono, '/'))))->toBeTrue("Falta {$icono}");
    }
});

test('las páginas enlazan el manifiesto y registran el service worker', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('<link rel="manifest" href="/manifest.json">', false)
        ->assertSee('<meta name="theme-color" content="#17222e">', false)
        ->assertSee('apple-mobile-web-app-capable', false)
        ->assertSee("navigator.serviceWorker.register('/sw.js'", false);
});
