<?php

use App\Enums\EstadoOrden;
use App\Enums\TipoServicio;
use App\Livewire\Coordinador\RevisionMedidores\RevisionMedidoresIndex;
use App\Mail\OrdenAsignada;
use App\Mail\OrdenesAsignadasResumen;
use App\Models\Company;
use App\Models\OrdenJob;
use App\Models\personals;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionsSeeder::class);
    $this->company = Company::factory()->create();
    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    $this->user->assignRole('Coordinador');
});

it('el componente renderiza correctamente', function () {
    $this->actingAs($this->user);

    Livewire::test(RevisionMedidoresIndex::class)
        ->assertOk()
        ->assertSee('Importar Órdenes');
});

it('muestra las órdenes de revisión de medidores', function () {
    $this->actingAs($this->user);

    $orden = OrdenJob::factory()->create([
        'tipo_servicio' => TipoServicio::RevisionMedidores->value,
        'company_id' => $this->company->id,
        'contrato' => 'CONT-TEST',
        'cliente' => 'Cliente Prueba',
        'estado' => EstadoOrden::Pendiente,
        'personals_id' => null,
    ]);

    Livewire::test(RevisionMedidoresIndex::class)
        ->assertSee($orden->contrato)
        ->assertSee($orden->cliente);
});

it('asignación individual envía un correo OrdenAsignada al investigador', function () {
    Mail::fake();
    $this->actingAs($this->user);

    $personal = personals::factory()->create(['company_id' => $this->company->id]);
    User::factory()->create([
        'personals_id' => $personal->id,
        'company_id' => $this->company->id,
        'email' => 'tecnico@test.com',
    ]);

    $orden = OrdenJob::factory()->create([
        'tipo_servicio' => TipoServicio::RevisionMedidores->value,
        'company_id' => $this->company->id,
        'estado' => EstadoOrden::Pendiente,
        'personals_id' => null,
    ]);

    Livewire::test(RevisionMedidoresIndex::class)
        ->callTableAction('asignar', $orden, data: [
            'personals_id' => $personal->id,
            'fecha_asignacion' => now()->toDateTimeString(),
            'fecha_limite' => null,
        ]);

    Mail::assertSent(OrdenAsignada::class, 1);
    Mail::assertNotSent(OrdenesAsignadasResumen::class);

    expect($orden->fresh()->estado)->toBe(EstadoOrden::Asignada);
    expect($orden->fresh()->personals_id)->toBe($personal->id);
});

it('asignación masiva envía un solo correo resumen y no envía correos individuales', function () {
    Mail::fake();
    $this->actingAs($this->user);

    $personal = personals::factory()->create(['company_id' => $this->company->id]);
    User::factory()->create([
        'personals_id' => $personal->id,
        'company_id' => $this->company->id,
        'email' => 'tecnico@test.com',
    ]);

    $ordenes = OrdenJob::factory()->count(3)->create([
        'tipo_servicio' => TipoServicio::RevisionMedidores->value,
        'company_id' => $this->company->id,
        'estado' => EstadoOrden::Pendiente,
        'personals_id' => null,
    ]);

    Livewire::test(RevisionMedidoresIndex::class)
        ->callTableBulkAction('asignar_masivo', $ordenes, data: [
            'personals_id' => $personal->id,
            'fecha_asignacion' => now()->toDateTimeString(),
            'fecha_limite' => null,
        ]);

    Mail::assertSent(OrdenesAsignadasResumen::class, 1);
    Mail::assertNotSent(OrdenAsignada::class);

    foreach ($ordenes as $orden) {
        expect($orden->fresh()->estado)->toBe(EstadoOrden::Asignada);
        expect($orden->fresh()->personals_id)->toBe($personal->id);
    }
});
