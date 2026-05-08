<?php

use App\Enums\EstadoOrden;
use App\Enums\TipoServicio;
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
});

test('guests are redirected to login on pdf download', function () {
    $orden = OrdenJob::factory()->create(['tipo_servicio' => TipoServicio::Fraudes->value]);

    $this->get(route('operaciones.ordenes-trabajo.reporte-pdf', $orden))
        ->assertRedirect(route('login'));
});

test('users without ordenes.ver permission cannot download pdf', function () {
    $user = User::factory()->create();
    $user->assignRole('Lector');

    $orden = OrdenJob::factory()->create(['tipo_servicio' => TipoServicio::Fraudes->value]);

    $this->actingAs($user)
        ->get(route('operaciones.ordenes-trabajo.reporte-pdf', $orden))
        ->assertForbidden();
});

test('pdf download returns 403 for non-fraudes service types', function () {
    $user = User::factory()->create();
    $user->assignRole('Coordinador');

    $orden = OrdenJob::factory()->create(['tipo_servicio' => TipoServicio::Investigacion->value]);

    $this->actingAs($user)
        ->get(route('operaciones.ordenes-trabajo.reporte-pdf', $orden))
        ->assertForbidden();
});

test('pdf download returns 404 when no reporte exists', function () {
    $user = User::factory()->create();
    $user->assignRole('Coordinador');

    $orden = OrdenJob::factory()->create([
        'tipo_servicio' => TipoServicio::Fraudes->value,
        'estado' => EstadoOrden::EnRevision->value,
    ]);

    $this->actingAs($user)
        ->get(route('operaciones.ordenes-trabajo.reporte-pdf', $orden))
        ->assertNotFound();
});

test('coordinador can download pdf for fraudes reporte', function () {
    Storage::fake('s3');

    $user = User::factory()->create();
    $user->assignRole('Coordinador');

    $orden = OrdenJob::factory()->create([
        'tipo_servicio' => TipoServicio::Fraudes->value,
        'estado' => EstadoOrden::EnRevision->value,
        'creado_por_id' => $user->id,
    ]);

    reportes::create([
        'orden_job_id' => $orden->id,
        'numero_orden' => $orden->numero_orden,
        'imagenes' => [],
        'observaciones' => 'Sin novedad.',
    ]);

    $response = $this->actingAs($user)
        ->get(route('operaciones.ordenes-trabajo.reporte-pdf', $orden));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
});
