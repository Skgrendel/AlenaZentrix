<?php

use App\Models\Company;
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

test('SetTenantContext sets company from authenticated user', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);

    $this->actingAs($user)->get(route('dashboard'));

    expect(app(TenantService::class)->id())->toBe($company->id);
});

test('superadmin hitting dashboard is redirected to superadmin panel', function () {
    $superadmin = User::factory()->create(['is_superadmin' => true, 'company_id' => null]);

    $this->actingAs($superadmin)
        ->get(route('dashboard'))
        ->assertRedirect(route('superadmin.dashboard'));
});

test('regular user cannot access superadmin routes', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole('Administrador');

    $this->actingAs($user)
        ->get(route('superadmin.dashboard'))
        ->assertForbidden();
});

test('superadmin can access superadmin panel', function () {
    $superadmin = User::factory()->create(['is_superadmin' => true, 'company_id' => null]);

    $this->actingAs($superadmin)
        ->get(route('superadmin.dashboard'))
        ->assertOk();
});

test('guest cannot access superadmin routes', function () {
    $this->get(route('superadmin.dashboard'))
        ->assertRedirect(route('login'));
});
