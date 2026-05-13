<?php

use App\Models\Company;
use App\Models\TipoAnomalia;
use App\Services\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function () {
    app(TenantService::class)->clear();
});

test('catalog items are scoped to their company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    TipoAnomalia::withoutGlobalScopes()->create([
        'nombre' => 'Anomalia A',
        'nomenclatura' => 'AA',
        'activo' => true,
        'company_id' => $companyA->id,
    ]);

    app(TenantService::class)->set($companyB);

    expect(TipoAnomalia::count())->toBe(0);
});

test('catalog items are auto-assigned company_id from tenant context', function () {
    $company = Company::factory()->create();
    app(TenantService::class)->set($company);

    TipoAnomalia::create(['nombre' => 'Test', 'nomenclatura' => 'T', 'activo' => true]);

    expect(TipoAnomalia::withoutGlobalScopes()->first()->company_id)->toBe($company->id);
});
