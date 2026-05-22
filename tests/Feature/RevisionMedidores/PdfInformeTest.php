<?php

use App\Enums\EstadoOrden;
use App\Enums\TipoServicio;
use App\Models\Company;
use App\Models\OrdenJob;
use App\Models\reportes;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    ini_set('memory_limit', '256M');
    $this->seed(PermissionsSeeder::class);
    $this->company = Company::factory()->create();
    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    $this->user->assignRole('Coordinador');
    Storage::fake('s3');
});

it('retorna 403 para órdenes que no son de revisión de medidores', function () {
    $orden = OrdenJob::factory()->create(['tipo_servicio' => TipoServicio::Fraudes->value]);

    $this->actingAs($this->user)
        ->get(route('operaciones.revision-medidores.reporte', $orden))
        ->assertForbidden();
});

it('retorna 404 cuando la orden no tiene reporte', function () {
    $orden = OrdenJob::factory()->create([
        'tipo_servicio' => TipoServicio::RevisionMedidores->value,
        'estado' => EstadoOrden::Completada->value,
    ]);

    $this->actingAs($this->user)
        ->get(route('operaciones.revision-medidores.reporte', $orden))
        ->assertNotFound();
});

it('descarga el PDF correctamente para una orden completada con reporte', function () {
    $orden = OrdenJob::factory()->create([
        'tipo_servicio' => TipoServicio::RevisionMedidores->value,
        'company_id' => $this->company->id,
        'estado' => EstadoOrden::Completada->value,
    ]);

    reportes::create([
        'orden_job_id' => $orden->id,
        'numero_orden' => $orden->numero_orden,
        'company_id' => $this->company->id,
        'imagenes' => [],
        'observaciones' => 'Sin novedad.',
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('operaciones.revision-medidores.reporte', $orden));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
});
