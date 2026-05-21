<?php

use App\Enums\TipoServicio;

it('tiene el case RevisionMedidores con valor correcto', function () {
    expect(TipoServicio::RevisionMedidores->value)->toBe('revision_medidores');
});

it('retorna el label correcto', function () {
    expect(TipoServicio::RevisionMedidores->label())->toBe('Revisión de Medidores');
});

it('retorna color distinto a los demás tipos', function () {
    $color = TipoServicio::RevisionMedidores->color();
    $otros = collect(TipoServicio::cases())
        ->filter(fn ($c) => $c !== TipoServicio::RevisionMedidores)
        ->map(fn ($c) => $c->color())
        ->unique();
    expect($otros->contains($color))->toBeFalse();
});

it('tieneContrato retorna true', function () {
    expect(TipoServicio::RevisionMedidores->tieneContrato())->toBeTrue();
});

it('tieneMedidor retorna true', function () {
    expect(TipoServicio::RevisionMedidores->tieneMedidor())->toBeTrue();
});

it('tieneTecnicoAsignado retorna true', function () {
    expect(TipoServicio::RevisionMedidores->tieneTecnicoAsignado())->toBeTrue();
});
