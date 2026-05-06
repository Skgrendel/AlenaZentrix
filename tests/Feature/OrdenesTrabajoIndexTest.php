<?php

use App\Enums\EstadoOrden;
use App\Models\OrdenJob;
use App\Models\personals;
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

test('orden created without tecnico stays pendiente', function () {
    $orden = OrdenJob::factory()->create(['personals_id' => null]);

    expect($orden->estado)->toBe(EstadoOrden::Pendiente);
});

test('orden created with tecnico is automatically set to asignada', function () {
    $tecnico = personals::factory()->create();
    $orden = OrdenJob::factory()->create(['personals_id' => $tecnico->id]);

    expect($orden->estado)->toBe(EstadoOrden::Asignada);
});

test('assigning tecnico to pendiente orden changes estado to asignada', function () {
    $orden = OrdenJob::factory()->create(['personals_id' => null]);
    expect($orden->estado)->toBe(EstadoOrden::Pendiente);

    $tecnico = personals::factory()->create();
    $orden->update(['personals_id' => $tecnico->id]);

    expect($orden->fresh()->estado)->toBe(EstadoOrden::Asignada);
});

test('changing tecnico on non-pendiente orden does not change estado', function () {
    $tecnico1 = personals::factory()->create();
    $tecnico2 = personals::factory()->create();
    $orden = OrdenJob::factory()->create([
        'personals_id' => $tecnico1->id,
        'estado' => EstadoOrden::EnProgreso,
    ]);

    $orden->update(['personals_id' => $tecnico2->id]);

    expect($orden->fresh()->estado)->toBe(EstadoOrden::EnProgreso);
});
