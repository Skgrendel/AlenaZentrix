<?php

use App\Enums\TipoServicio;
use App\Livewire\Mapa\MapaIndex;
use App\Models\Company;
use App\Models\OrdenJob;
use App\Models\personals;
use App\Models\reportes;
use App\Models\ubicacion;
use App\Models\User;
use App\Services\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Permission::create(['name' => 'mapa.ver', 'guard_name' => 'web']);

    Role::create(['name' => 'Administrador', 'guard_name' => 'web'])
        ->givePermissionTo('mapa.ver');
    Role::create(['name' => 'Coordinador', 'guard_name' => 'web'])
        ->givePermissionTo('mapa.ver');
    Role::create(['name' => 'Lector', 'guard_name' => 'web']);
});

test('guests are redirected to login', function () {
    $this->get(route('mapa.index'))
        ->assertRedirect(route('login'));
});

test('users without mapa.ver permission are forbidden', function () {
    $user = User::factory()->create();
    $user->assignRole('Lector');

    $this->actingAs($user)
        ->get(route('mapa.index'))
        ->assertForbidden();
});

test('coordinador with mapa.ver can access the map', function () {
    $user = User::factory()->create();
    $user->assignRole('Coordinador');

    $this->actingAs($user)
        ->get(route('mapa.index'))
        ->assertOk();
});

test('administrador with mapa.ver can access the map', function () {
    $user = User::factory()->create();
    $user->assignRole('Administrador');

    $this->actingAs($user)
        ->get(route('mapa.index'))
        ->assertOk();
});

test('only reportes with non-null lat and lng are returned as marcadores', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole('Coordinador');

    app(TenantService::class)->set($company);

    $orden = OrdenJob::factory()->create(['tipo_servicio' => TipoServicio::Fraudes->value, 'company_id' => $company->id]);

    $reporteConCoordenadas = reportes::factory()->create([
        'orden_job_id' => $orden->id,
        'company_id' => $company->id,
        'ubicacions_id' => ubicacion::factory()->create([
            'latitud' => '4.7110',
            'longitud' => '-74.0721',
        ])->id,
        'estado' => 'pendiente',
    ]);

    $reporteSinUbicacion = reportes::factory()->sinUbicacion()->create([
        'orden_job_id' => $orden->id,
        'company_id' => $company->id,
    ]);

    $reporteConCoordenadasNulas = reportes::factory()->conUbicacionSinCoordenadas()->create([
        'orden_job_id' => $orden->id,
        'company_id' => $company->id,
    ]);

    $component = Livewire\Livewire::actingAs($user)
        ->test(MapaIndex::class);

    $marcadores = $component->instance()->marcadores;

    expect($marcadores)->toHaveCount(1)
        ->and($marcadores[0]['numero_orden'])->toBe($reporteConCoordenadas->numero_orden);
});

test('filter by fecha reduces marcadores', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole('Coordinador');

    app(TenantService::class)->set($company);

    $orden = OrdenJob::factory()->create(['tipo_servicio' => TipoServicio::Fraudes->value, 'company_id' => $company->id]);

    reportes::factory()->create([
        'orden_job_id' => $orden->id,
        'company_id' => $company->id,
        'ubicacions_id' => ubicacion::factory()->create(['latitud' => '4.71', 'longitud' => '-74.07'])->id,
        'created_at' => now()->subDays(5),
    ]);

    reportes::factory()->create([
        'orden_job_id' => $orden->id,
        'company_id' => $company->id,
        'ubicacions_id' => ubicacion::factory()->create(['latitud' => '4.71', 'longitud' => '-74.07'])->id,
        'created_at' => now()->subDays(60),
    ]);

    $component = Livewire\Livewire::actingAs($user)
        ->test(MapaIndex::class)
        ->set('fechaDesde', now()->subDays(30)->toDateString())
        ->set('fechaHasta', now()->toDateString());

    expect($component->instance()->marcadores)->toHaveCount(1);
});

test('filter by tecnico reduces marcadores', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole('Coordinador');

    app(TenantService::class)->set($company);

    $orden = OrdenJob::factory()->create(['tipo_servicio' => TipoServicio::Fraudes->value, 'company_id' => $company->id]);
    $tecnico = personals::factory()->create(['company_id' => $company->id]);

    reportes::factory()->create([
        'orden_job_id' => $orden->id,
        'company_id' => $company->id,
        'ubicacions_id' => ubicacion::factory()->create(['latitud' => '4.71', 'longitud' => '-74.07'])->id,
        'personals_id' => $tecnico->id,
    ]);

    reportes::factory()->create([
        'orden_job_id' => $orden->id,
        'company_id' => $company->id,
        'ubicacions_id' => ubicacion::factory()->create(['latitud' => '5.00', 'longitud' => '-75.00'])->id,
        'personals_id' => null,
    ]);

    $component = Livewire\Livewire::actingAs($user)
        ->test(MapaIndex::class)
        ->set('tecnicoId', $tecnico->id);

    expect($component->instance()->marcadores)->toHaveCount(1);
});

test('filter by estado reduces marcadores', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole('Coordinador');

    app(TenantService::class)->set($company);

    $orden = OrdenJob::factory()->create(['tipo_servicio' => TipoServicio::Fraudes->value, 'company_id' => $company->id]);

    reportes::factory()->estado('confirmado')->create([
        'orden_job_id' => $orden->id,
        'company_id' => $company->id,
        'ubicacions_id' => ubicacion::factory()->create(['latitud' => '4.71', 'longitud' => '-74.07'])->id,
    ]);

    reportes::factory()->estado('pendiente')->create([
        'orden_job_id' => $orden->id,
        'company_id' => $company->id,
        'ubicacions_id' => ubicacion::factory()->create(['latitud' => '5.00', 'longitud' => '-75.00'])->id,
    ]);

    $component = Livewire\Livewire::actingAs($user)
        ->test(MapaIndex::class)
        ->set('estadoReporte', 'confirmado');

    expect($component->instance()->marcadores)->toHaveCount(1);
});

test('filter by numero_orden reduces marcadores', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole('Coordinador');

    app(TenantService::class)->set($company);

    $orden = OrdenJob::factory()->create(['tipo_servicio' => TipoServicio::Fraudes->value, 'company_id' => $company->id]);

    reportes::factory()->create([
        'orden_job_id' => $orden->id,
        'company_id' => $company->id,
        'ubicacions_id' => ubicacion::factory()->create(['latitud' => '4.71', 'longitud' => '-74.07'])->id,
        'numero_orden' => 'ORD-0001',
    ]);

    reportes::factory()->create([
        'orden_job_id' => $orden->id,
        'company_id' => $company->id,
        'ubicacions_id' => ubicacion::factory()->create(['latitud' => '5.00', 'longitud' => '-75.00'])->id,
        'numero_orden' => 'ORD-9999',
    ]);

    $component = Livewire\Livewire::actingAs($user)
        ->test(MapaIndex::class)
        ->set('ordenTrabajo', '0001');

    expect($component->instance()->marcadores)->toHaveCount(1);
});
