<?php

use App\Enums\TipoServicio;
use App\Livewire\Coordinador\OrdenCreate;
use App\Livewire\Coordinador\OrdenesTrabajoIndex;
use App\Models\OrdenJob;
use App\Models\OrigenInformacion;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionsSeeder::class);
});

it('resuelve la relacion origenInformacion en OrdenJob', function () {
    $origen = OrigenInformacion::create(['nombre' => 'Denuncia Ciudadana', 'activo' => true]);
    $orden = OrdenJob::factory()->create(['origen_informacion_id' => $origen->id]);

    expect($orden->origenInformacion->id)->toBe($origen->id)
        ->and($orden->origenInformacion->nombre)->toBe('Denuncia Ciudadana');
});

it('permite crear una orden con origen de informacion asignado', function () {
    $user = User::factory()->create();
    $user->assignRole('Coordinador');
    $origen = OrigenInformacion::create(['nombre' => 'Reporte Interno', 'activo' => true]);

    $this->actingAs($user);

    Livewire::test(OrdenCreate::class)
        ->set('data.tipo_servicio', TipoServicio::Fraudes->value)
        ->set('data.origen_informacion_id', $origen->id)
        ->set('data.contrato', 'CONT-0001')
        ->set('data.cliente', 'Cliente de prueba')
        ->set('data.direccion', 'Calle 123')
        ->set('data.barrio', 'Centro')
        ->set('data.medidor', 'MED-001')
        ->set('data.tipo_medidor', 'digital')
        ->call('guardar');

    expect(OrdenJob::first()->origen_informacion_id)->toBe($origen->id);
});

it('permite crear una orden sin origen de informacion (queda null)', function () {
    $user = User::factory()->create();
    $user->assignRole('Coordinador');

    $this->actingAs($user);

    Livewire::test(OrdenCreate::class)
        ->set('data.tipo_servicio', TipoServicio::Fraudes->value)
        ->set('data.contrato', 'CONT-0002')
        ->set('data.cliente', 'Cliente de prueba')
        ->set('data.direccion', 'Calle 123')
        ->set('data.barrio', 'Centro')
        ->set('data.medidor', 'MED-002')
        ->set('data.tipo_medidor', 'digital')
        ->call('guardar');

    expect(OrdenJob::first()->origen_informacion_id)->toBeNull();
});

it('filtra el listado de ordenes por origen de informacion', function () {
    $user = User::factory()->create();
    $user->assignRole('Coordinador');

    $origenA = OrigenInformacion::create(['nombre' => 'Origen A', 'activo' => true]);
    $origenB = OrigenInformacion::create(['nombre' => 'Origen B', 'activo' => true]);

    $ordenA = OrdenJob::factory()->create(['origen_informacion_id' => $origenA->id]);
    $ordenB = OrdenJob::factory()->create(['origen_informacion_id' => $origenB->id]);

    $this->actingAs($user);

    Livewire::test(OrdenesTrabajoIndex::class)
        ->filterTable('origen_informacion_id', $origenA->id)
        ->assertCanSeeTableRecords([$ordenA])
        ->assertCanNotSeeTableRecords([$ordenB]);
});

it('la columna Origen de Informacion se oculta cuando ningun registro tiene datos', function () {
    $user = User::factory()->create();
    $user->assignRole('Coordinador');

    OrdenJob::factory()->create(['origen_informacion_id' => null]);

    $this->actingAs($user);

    $instance = Livewire::test(OrdenesTrabajoIndex::class)->instance();

    expect($instance->getTable()->getColumn('origenInformacion.nombre')->isVisible())->toBeFalse();
});

it('la columna Origen de Informacion se muestra cuando algun registro tiene datos', function () {
    $user = User::factory()->create();
    $user->assignRole('Coordinador');

    $origen = OrigenInformacion::create(['nombre' => 'Origen C', 'activo' => true]);
    OrdenJob::factory()->create(['origen_informacion_id' => $origen->id]);

    $this->actingAs($user);

    $instance = Livewire::test(OrdenesTrabajoIndex::class)->instance();

    expect($instance->getTable()->getColumn('origenInformacion.nombre')->isVisible())->toBeTrue();
});

it('el catalogo Origen de Informacion es administrable desde Configuración', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrador');

    $this->actingAs($user)
        ->get(route('admin.configuracion'))
        ->assertOk()
        ->assertSee('Origen de Información');
});
