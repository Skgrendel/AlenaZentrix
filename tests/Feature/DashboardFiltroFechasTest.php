<?php

use App\Enums\EstadoOrden;
use App\Filament\Widgets\OrdenesStatsOverview;
use App\Filament\Widgets\RendimientoInvestigadoresWidget;
use App\Livewire\DashboardFiltroFechas;
use App\Models\Company;
use App\Models\OrdenJob;
use App\Models\personals;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Permission::create(['name' => 'ordenes.ver', 'guard_name' => 'web']);

    Role::create(['name' => 'Coordinador', 'guard_name' => 'web'])
        ->givePermissionTo('ordenes.ver');
});

test('dashboard renders with the date filter and every widget', function () {
    $user = User::factory()->create();
    $user->assignRole('Coordinador');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSeeLivewire(DashboardFiltroFechas::class)
        ->assertSeeLivewire(OrdenesStatsOverview::class)
        ->assertSeeLivewire(RendimientoInvestigadoresWidget::class);
});

test('filtro de fechas dispatches the dashboard event with the selected range on aplicar', function () {
    Livewire::test(DashboardFiltroFechas::class)
        ->set('desde', '2026-06-01')
        ->set('hasta', '2026-06-30')
        ->call('aplicar')
        ->assertDispatched('dashboard-fecha-filtrada', desde: '2026-06-01', hasta: '2026-06-30');
});

test('filtro de fechas clears the range and dispatches null values on limpiar', function () {
    Livewire::test(DashboardFiltroFechas::class)
        ->set('desde', '2026-06-01')
        ->set('hasta', '2026-06-30')
        ->call('limpiar')
        ->assertSet('desde', null)
        ->assertSet('hasta', null)
        ->assertDispatched('dashboard-fecha-filtrada', desde: null, hasta: null);
});

test('ordenes stats overview only counts ordenes within the selected date range', function () {
    $user = User::factory()->create();
    $user->assignRole('Coordinador');
    $this->actingAs($user);

    OrdenJob::factory()->create(['created_at' => '2026-06-15', 'estado' => EstadoOrden::Completada]);
    OrdenJob::factory()->create(['created_at' => '2026-05-01', 'estado' => EstadoOrden::Completada]);

    $widget = new OrdenesStatsOverview;
    $widget->desde = '2026-06-01';
    $widget->hasta = '2026-06-30';

    $stats = (fn () => $this->getStats())->call($widget);

    expect($stats[0]->getValue())->toBe(1)
        ->and($stats[4]->getValue())->toBe(1);
});

test('rendimiento investigadores widget only counts ordenes within the selected date range', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole('Coordinador');
    $this->actingAs($user);

    $investigador = personals::factory()->create(['company_id' => $company->id]);

    OrdenJob::factory()->create([
        'company_id' => $company->id,
        'personals_id' => $investigador->id,
        'created_at' => '2026-06-15',
    ]);
    OrdenJob::factory()->create([
        'company_id' => $company->id,
        'personals_id' => $investigador->id,
        'created_at' => '2026-05-01',
    ]);

    $widget = new RendimientoInvestigadoresWidget;
    $widget->desde = '2026-06-01';
    $widget->hasta = '2026-06-30';

    $resultado = $widget->getInvestigadores()->firstWhere('nombres', $investigador->nombres);

    expect((int) $resultado->total)->toBe(1);
});
