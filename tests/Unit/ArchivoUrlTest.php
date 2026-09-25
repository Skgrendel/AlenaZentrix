<?php

use App\Support\ArchivoUrl;

test('esReferenciaTemporal detects unresolved livewire temporary upload references', function () {
    expect(ArchivoUrl::esReferenciaTemporal('livewire-file:cBgjMvKLVl6iY4oNK3HlZ5AM6JilPLBuTibZ'))->toBeTrue();
});

test('esReferenciaTemporal returns false for real stored paths', function () {
    expect(ArchivoUrl::esReferenciaTemporal('reportes/foto-real.jpg'))->toBeFalse()
        ->and(ArchivoUrl::esReferenciaTemporal('auditorias/evidencia.JPE'))->toBeFalse();
});
