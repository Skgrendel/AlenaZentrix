<?php

use App\Enums\EstadoOrden;
use App\Enums\PrioridadOrden;
use App\Enums\TipoServicio;
use App\Livewire\Coordinador\OrdenesTrabajoIndex;
use App\Models\OrdenJob;
use App\Models\personals;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Permission::create(['name' => 'ordenes.ver', 'guard_name' => 'web']);
    Permission::create(['name' => 'ordenes.crear', 'guard_name' => 'web']);

    Role::create(['name' => 'Administrador', 'guard_name' => 'web'])
        ->givePermissionTo('ordenes.ver');
    Role::create(['name' => 'Coordinador', 'guard_name' => 'web'])
        ->givePermissionTo('ordenes.ver');
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

test('users with ordenes.crear can see the new order action', function () {
    $user = User::factory()->create();
    $user->assignRole('Coordinador');
    $user->givePermissionTo('ordenes.crear');

    $this->actingAs($user)
        ->get(route('operaciones.ordenes-trabajo'))
        ->assertOk()
        ->assertSee('Nueva Orden');
});

test('users without ordenes.crear cannot see the new order action', function () {
    $user = User::factory()->create();
    $user->assignRole('Coordinador');

    $this->actingAs($user)
        ->get(route('operaciones.ordenes-trabajo'))
        ->assertOk()
        ->assertDontSee('Nueva Orden');
});

test('administrador can access ordenes de trabajo', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrador');

    $this->actingAs($user)
        ->get(route('operaciones.ordenes-trabajo'))
        ->assertOk();
});

test('empty cliente contrato and barrio columns are hidden when no records have data', function () {
    $user = User::factory()->create();
    $user->assignRole('Coordinador');

    OrdenJob::create([
        'tipo_servicio' => TipoServicio::Fraudes,
        'contrato' => null,
        'cliente' => null,
        'direccion' => null,
        'barrio' => null,
        'medidor' => null,
        'tipo_medidor' => null,
        'ciclo' => null,
        'numero_noticia_criminal' => null,
        'tipo_investigacion' => null,
        'documentos' => null,
        'prioridad' => PrioridadOrden::Media,
        'estado' => EstadoOrden::Pendiente,
        'estado_servicio' => true,
        'fecha_limite' => null,
        'fecha_completado' => null,
    ]);

    $this->actingAs($user)
        ->get(route('operaciones.ordenes-trabajo'))
        ->assertOk()
        ->assertDontSee('Cliente')
        ->assertDontSee('Contrato')
        ->assertDontSee('Barrio');
});

test('cliente contrato and barrio columns are shown when records have data', function () {
    $user = User::factory()->create();
    $user->assignRole('Coordinador');

    OrdenJob::create([
        'tipo_servicio' => TipoServicio::Fraudes,
        'contrato' => 'CONT-1234',
        'cliente' => 'Cliente de prueba',
        'direccion' => 'Dirección de prueba',
        'barrio' => 'Centro',
        'medidor' => null,
        'tipo_medidor' => null,
        'ciclo' => null,
        'numero_noticia_criminal' => null,
        'tipo_investigacion' => null,
        'documentos' => null,
        'prioridad' => PrioridadOrden::Media,
        'estado' => EstadoOrden::Pendiente,
        'estado_servicio' => true,
        'fecha_limite' => null,
        'fecha_completado' => null,
    ]);

    $this->actingAs($user)
        ->get(route('operaciones.ordenes-trabajo'))
        ->assertOk()
        ->assertSee('Cliente')
        ->assertSee('Contrato')
        ->assertSee('Barrio');
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

test('created_at date filter only shows ordenes within the selected range', function () {
    $user = User::factory()->create();
    $user->assignRole('Coordinador');

    $dentroDelRango = OrdenJob::factory()->create(['created_at' => '2026-06-15']);
    $fueraDelRango = OrdenJob::factory()->create(['created_at' => '2026-05-01']);

    $this->actingAs($user);

    livewire(OrdenesTrabajoIndex::class)
        ->filterTable('created_at', [
            'creado_desde' => '2026-06-01',
            'creado_hasta' => '2026-06-30',
        ])
        ->assertCanSeeTableRecords([$dentroDelRango])
        ->assertCanNotSeeTableRecords([$fueraDelRango]);
});
