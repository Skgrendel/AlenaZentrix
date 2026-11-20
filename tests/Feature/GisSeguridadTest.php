<?php

use App\Services\coordinador\DataGisServices;

/** @return array<int, string> rutas de los servicios del GIS */
function archivosDelGis(): array
{
    return glob(app_path('Services/coordinador/DataGis*.php'));
}

test('los servicios del GIS no desactivan la verificación de certificados', function () {
    expect(archivosDelGis())->not->toBeEmpty();

    foreach (archivosDelGis() as $archivo) {
        expect(file_get_contents($archivo))->not->toContain('withoutVerifying', basename($archivo).' desactiva la verificación de certificados');
    }
});

test('no hay cookies de sesión ni tokens escritos en el código del GIS', function () {
    foreach (archivosDelGis() as $archivo) {
        $codigo = file_get_contents($archivo);

        expect($codigo)->not->toContain("'Cookie'")
            ->and($codigo)->not->toContain('AGS_ROLES')
            ->and($codigo)->not->toContain('esri_auth');
    }
});

test('las credenciales del GIS se leen de la configuración y no de env()', function () {
    foreach (archivosDelGis() as $archivo) {
        expect(file_get_contents($archivo))->not->toContain('env(');
    }

    expect(array_keys(config('services.gis')))->toContain('usuario', 'password', 'verificar_certificado');
});

test('la verificación del certificado del GIS está activada por defecto', function () {
    expect(config('services.gis.verificar_certificado'))->toBeTrue();
});

test('la verificación se puede apagar temporalmente desde el entorno y vuelve a su valor al quitarla', function () {
    $valor = fn (string $texto) => filter_var($texto, FILTER_VALIDATE_BOOLEAN);

    expect($valor('false'))->toBeFalse()
        ->and($valor('0'))->toBeFalse()
        ->and($valor('true'))->toBeTrue();
});

test('la clase duplicada del GIS que nadie usaba ya no existe', function () {
    expect(file_exists(app_path('Services/coordinador/DataGisServicesImproved.php')))->toBeFalse()
        ->and(class_exists(DataGisServices::class))->toBeTrue();
});
