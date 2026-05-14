<?php

use App\Enums\TipoServicio;

it('AuditoriaCampo enum case exists with correct label and color', function () {
    $case = TipoServicio::AuditoriaCampo;
    expect($case->label())->toBe('Auditorías de Campo')
        ->and($case->color())->toBe('purple')
        ->and($case->tieneTecnicoAsignado())->toBeTrue();
});
