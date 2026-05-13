<?php

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a company can be created via the factory', function () {
    $company = Company::factory()->create();

    expect($company)->toBeInstanceOf(Company::class)
        ->and($company->exists)->toBeTrue()
        ->and($company->nombre)->toBeString()
        ->and($company->activo)->toBeTrue();
});

test('the inactiva state sets activo to false', function () {
    $company = Company::factory()->inactiva()->create();

    expect($company->activo)->toBeFalse();
});
