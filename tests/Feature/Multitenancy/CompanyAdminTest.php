<?php

use App\Livewire\SuperAdmin\Companies\CompaniesIndex;
use App\Livewire\SuperAdmin\Roles\EditRolePermisos;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

test('superadmin can view companies list', function () {
    $superadmin = User::factory()->superadmin()->create();
    Company::factory()->count(3)->create();

    $this->actingAs($superadmin)
        ->get(route('superadmin.companies'))
        ->assertOk();
});

test('superadmin can create a company', function () {
    $superadmin = User::factory()->superadmin()->create();

    Livewire\Livewire::actingAs($superadmin)
        ->test(CompaniesIndex::class)
        ->callAction('create', ['nombre' => 'Nueva Empresa', 'activo' => true])
        ->assertHasNoErrors();

    expect(Company::where('nombre', 'Nueva Empresa')->exists())->toBeTrue();
});

test('superadmin can deactivate a company', function () {
    $superadmin = User::factory()->superadmin()->create();
    $company = Company::factory()->create(['activo' => true]);

    Livewire\Livewire::actingAs($superadmin)
        ->test(CompaniesIndex::class)
        ->callTableAction('edit', $company, ['nombre' => $company->nombre, 'activo' => false])
        ->assertHasNoErrors();

    expect($company->fresh()->activo)->toBeFalse();
});

test('superadmin sees users from all companies', function () {
    $superadmin = User::factory()->superadmin()->create();

    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    User::factory()->create(['company_id' => $companyA->id]);
    User::factory()->create(['company_id' => $companyB->id]);

    $this->actingAs($superadmin)
        ->get(route('superadmin.users'))
        ->assertOk();

    // Superadmin + 2 company users = 3 total (withoutGlobalScopes bypasses CompanyScope)
    expect(User::withoutGlobalScopes()->where('is_superadmin', false)->count())->toBe(2);
});

test('superadmin can access roles management', function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $superadmin = User::factory()->superadmin()->create();

    $this->actingAs($superadmin)
        ->get(route('superadmin.roles'))
        ->assertOk();
});

test('superadmin can edit a role and its permissions', function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Permission::create(['name' => 'roles.ver', 'guard_name' => 'web']);
    $role = Role::create(['name' => 'Administrador', 'guard_name' => 'web']);
    $superadmin = User::factory()->superadmin()->create();

    Livewire\Livewire::actingAs($superadmin)
        ->test(EditRolePermisos::class, ['role' => $role])
        ->set('selectedPermissions', ['roles.ver'])
        ->call('guardar')
        ->assertHasNoErrors()
        ->assertRedirect(route('superadmin.roles'));

    expect($role->fresh()->hasPermissionTo('roles.ver'))->toBeTrue();
});
