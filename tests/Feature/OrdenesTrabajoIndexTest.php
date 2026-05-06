<?php

use App\Models\OrdenJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Role::create(['name' => 'Administrador', 'guard_name' => 'web']);
    Role::create(['name' => 'Coordinador', 'guard_name' => 'web']);
    Role::create(['name' => 'Lector', 'guard_name' => 'web']);
});

test('guests are redirected to login', function () {
    $this->get(route('operaciones.ordenes-trabajo'))
        ->assertRedirect(route('login'));
});

test('users without the required role are forbidden', function () {
    $user = User::factory()->create();
    $user->assignRole('Lector');

    $this->actingAs($user)
        ->get(route('operaciones.ordenes-trabajo'))
        ->assertForbidden();
});

test('coordinador can access ordenes de trabajo', function () {
    $user = User::factory()->create();
    $user->assignRole('Coordinador');

    $this->actingAs($user)
        ->get(route('operaciones.ordenes-trabajo'))
        ->assertOk();
});

test('administrador can access ordenes de trabajo', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrador');

    $this->actingAs($user)
        ->get(route('operaciones.ordenes-trabajo'))
        ->assertOk();
});

test('creating an orden job auto generates numero orden with ODT prefix', function () {
    $orden = OrdenJob::factory()->create();

    expect($orden->numero_orden)
        ->toStartWith('ODT-')
        ->toHaveLength(10);
});

test('numero orden is sequential', function () {
    $primera = OrdenJob::factory()->create();
    $segunda = OrdenJob::factory()->create();

    expect($primera->numero_orden)->toBe('ODT-000001');
    expect($segunda->numero_orden)->toBe('ODT-000002');
});

test('numero orden is not overwritten if provided explicitly', function () {
    $orden = OrdenJob::factory()->create(['numero_orden' => 'ODT-999999']);

    expect($orden->numero_orden)->toBe('ODT-999999');
});
