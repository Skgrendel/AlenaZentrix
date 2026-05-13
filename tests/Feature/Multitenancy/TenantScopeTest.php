<?php

use App\Models\Company;
use App\Models\OrdenJob;
use App\Models\personals;
use App\Models\reportes;
use App\Models\User;
use App\Services\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::create(['name' => 'ordenes.ver', 'guard_name' => 'web']);
    Role::create(['name' => 'Administrador', 'guard_name' => 'web'])->givePermissionTo('ordenes.ver');
});

test('TenantService starts empty', function () {
    $service = app(TenantService::class);

    expect($service->active())->toBeFalse()
        ->and($service->id())->toBeNull()
        ->and($service->get())->toBeNull();
});

test('TenantService set and get work', function () {
    $company = Company::factory()->create();
    $service = app(TenantService::class);

    $service->set($company);

    expect($service->active())->toBeTrue()
        ->and($service->id())->toBe($company->id)
        ->and($service->get())->toBe($company);
});

test('CompanyScope filters users by company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $userA = User::factory()->create(['company_id' => $companyA->id]);
    User::factory()->create(['company_id' => $companyB->id]);

    app(TenantService::class)->set($companyA);

    expect(User::all())->toHaveCount(1)
        ->and(User::first()->id)->toBe($userA->id);
});

test('CompanyScope does not filter when user is superadmin', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $superadmin = User::factory()->create(['company_id' => null, 'is_superadmin' => true]);
    User::factory()->create(['company_id' => $companyA->id]);
    User::factory()->create(['company_id' => $companyB->id]);

    $this->actingAs($superadmin);

    expect(User::all())->toHaveCount(3);
});

test('CompanyScope filters personals by company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $personalA = personals::factory()->create(['company_id' => $companyA->id]);
    personals::factory()->create(['company_id' => $companyB->id]);

    app(TenantService::class)->set($companyA);

    expect(personals::all())->toHaveCount(1)
        ->and(personals::first()->id)->toBe($personalA->id);
});

test('CompanyScope filters orden_jobs by company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $ordenA = OrdenJob::factory()->create(['company_id' => $companyA->id]);
    OrdenJob::factory()->create(['company_id' => $companyB->id]);

    app(TenantService::class)->set($companyA);

    expect(OrdenJob::all())->toHaveCount(1)
        ->and(OrdenJob::first()->id)->toBe($ordenA->id);
});

test('CompanyScope filters reportes by company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $ordenA = OrdenJob::factory()->create(['company_id' => $companyA->id]);
    $ordenB = OrdenJob::factory()->create(['company_id' => $companyB->id]);

    $reporteA = reportes::factory()->create(['company_id' => $companyA->id, 'orden_job_id' => $ordenA->id]);
    reportes::factory()->create(['company_id' => $companyB->id, 'orden_job_id' => $ordenB->id]);

    app(TenantService::class)->set($companyA);

    expect(reportes::all())->toHaveCount(1)
        ->and(reportes::first()->id)->toBe($reporteA->id);
});
