<?php

use App\Enums\EstadoAuditoria;
use App\Enums\EstadoOrden;
use App\Enums\PrioridadOrden;
use App\Enums\TipoServicio;
use App\Filament\Widgets\AnomaliasDetectadasChart;
use App\Filament\Widgets\AuditoriaPorEstadoChart;
use App\Filament\Widgets\OrdenesPorPrioridadChart;
use App\Filament\Widgets\OrdenesPorProcesoChart;
use App\Filament\Widgets\OrdenesPorResultadoChart;
use App\Filament\Widgets\OrdenesStatsOverview;
use App\Livewire\DashboardFiltros;
use App\Models\auditoria;
use App\Models\Company;
use App\Models\OrdenJob;
use App\Models\Proceso;
use App\Models\reportes;
use App\Models\TipoAnomalia;
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

test('dashboard renders with the filter bar and every widget', function () {
    $user = User::factory()->create();
    $user->assignRole('Coordinador');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSeeLivewire(DashboardFiltros::class)
        ->assertSeeLivewire(OrdenesStatsOverview::class);
});

test('filtro del panel dispatches the dashboard event with every selected criterion on aplicar', function () {
    Livewire::test(DashboardFiltros::class)
        ->set('desde', '2026-06-01')
        ->set('hasta', '2026-06-30')
        ->set('estado', EstadoOrden::Completada->value)
        ->set('tipoServicio', TipoServicio::Fraudes->value)
        ->call('aplicar')
        ->assertDispatched(
            'dashboard-filtros-aplicados',
            desde: '2026-06-01',
            hasta: '2026-06-30',
            estado: EstadoOrden::Completada->value,
            tipoServicio: TipoServicio::Fraudes->value,
        );
});

test('filtro del panel clears every criterion and dispatches null values on limpiar', function () {
    Livewire::test(DashboardFiltros::class)
        ->set('desde', '2026-06-01')
        ->set('estado', EstadoOrden::Completada->value)
        ->call('limpiar')
        ->assertSet('desde', null)
        ->assertSet('estado', null)
        ->assertDispatched('dashboard-filtros-aplicados', desde: null, estado: null);
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

test('ordenes stats overview counts auditorias pendientes por auditar as entregadas pendiente de revision', function () {
    $user = User::factory()->create();
    $user->assignRole('Coordinador');
    $this->actingAs($user);

    $ordenPendiente = OrdenJob::factory()->create(['tipo_servicio' => TipoServicio::Fraudes]);
    $reportePendiente = reportes::factory()->create(['orden_job_id' => $ordenPendiente->id, 'numero_orden' => $ordenPendiente->numero_orden]);
    auditoria::create(['reportes_id' => $reportePendiente->id, 'estado' => EstadoAuditoria::PendienteAuditoria]);

    $ordenConfirmada = OrdenJob::factory()->create(['tipo_servicio' => TipoServicio::Fraudes]);
    $reporteConfirmada = reportes::factory()->create(['orden_job_id' => $ordenConfirmada->id, 'numero_orden' => $ordenConfirmada->numero_orden]);
    auditoria::create(['reportes_id' => $reporteConfirmada->id, 'estado' => EstadoAuditoria::Confirmado]);

    $widget = new OrdenesStatsOverview;

    $stats = (fn () => $this->getStats())->call($widget);

    expect($stats[5]->getLabel())->toBe('Entregadas pendiente de Revisión')
        ->and($stats[5]->getValue())->toBe(1);
});

test('ordenes stats overview only counts ordenes matching the selected tipo_servicio criterio', function () {
    $user = User::factory()->create();
    $user->assignRole('Coordinador');
    $this->actingAs($user);

    OrdenJob::factory()->create(['tipo_servicio' => TipoServicio::Fraudes]);
    OrdenJob::factory()->create(['tipo_servicio' => TipoServicio::Investigacion]);

    $widget = new OrdenesStatsOverview;
    $widget->tipoServicio = TipoServicio::Fraudes->value;

    $stats = (fn () => $this->getStats())->call($widget);

    expect($stats[0]->getValue())->toBe(1);
});

test('ordenes por prioridad chart only counts ordenes matching the selected estado criterio', function () {
    OrdenJob::factory()->create(['prioridad' => PrioridadOrden::Alta, 'estado' => EstadoOrden::Completada]);
    OrdenJob::factory()->create(['prioridad' => PrioridadOrden::Alta, 'estado' => EstadoOrden::Pendiente]);
    OrdenJob::factory()->create(['prioridad' => PrioridadOrden::Baja, 'estado' => EstadoOrden::Completada]);

    $widget = new OrdenesPorPrioridadChart;
    $widget->estado = EstadoOrden::Completada->value;

    $data = (fn () => $this->getData())->call($widget);

    expect($data['labels'])->toBe(['Baja', 'Alta'])
        ->and($data['datasets'][0]['data'])->toBe([1, 1]);
});

test('ordenes por resultado chart groups positiva negativa and sin resultado', function () {
    OrdenJob::factory()->create(['resultado' => 'positivo']);
    OrdenJob::factory()->create(['resultado' => 'negativo']);
    OrdenJob::factory()->create(['resultado' => 'negativo']);
    OrdenJob::factory()->create(['resultado' => 'pendiente']);

    $widget = new OrdenesPorResultadoChart;

    $data = (fn () => $this->getData())->call($widget);

    expect($data['labels'])->toBe(['Positiva', 'Negativa', 'Sin Resultado'])
        ->and($data['datasets'][0]['data'])->toBe([1, 2, 1]);
});

test('auditoria por estado chart only counts auditorias whose orden matches the selected criterio', function () {
    $reporteCoincide = reportes::factory()->create();
    auditoria::create(['reportes_id' => $reporteCoincide->id, 'estado' => EstadoAuditoria::Confirmado]);

    $reporteNoCoincide = reportes::factory()->create();
    $reporteNoCoincide->ordenJob->update(['tipo_servicio' => TipoServicio::Investigacion]);
    auditoria::create(['reportes_id' => $reporteNoCoincide->id, 'estado' => EstadoAuditoria::Confirmado]);

    $widget = new AuditoriaPorEstadoChart;
    $widget->tipoServicio = TipoServicio::Fraudes->value;

    $data = (fn () => $this->getData())->call($widget);

    expect($data['labels'])->toBe(['Confirmado'])
        ->and($data['datasets'][0]['data'])->toBe([1]);
});

test('anomalias detectadas chart lists the most frequent tipos de anomalia', function () {
    $company = Company::factory()->create();
    $bypass = TipoAnomalia::create(['nombre' => 'Bypass', 'company_id' => $company->id]);
    $sellosRotos = TipoAnomalia::create(['nombre' => 'Sellos Rotos', 'company_id' => $company->id]);

    auditoria::create(['reportes_id' => reportes::factory()->create()->id, 'estado' => EstadoAuditoria::Confirmado, 'tipo_anomalia_id' => $bypass->id]);
    auditoria::create(['reportes_id' => reportes::factory()->create()->id, 'estado' => EstadoAuditoria::Confirmado, 'tipo_anomalia_id' => $bypass->id]);
    auditoria::create(['reportes_id' => reportes::factory()->create()->id, 'estado' => EstadoAuditoria::Confirmado, 'tipo_anomalia_id' => $sellosRotos->id]);

    $widget = new AnomaliasDetectadasChart;

    $data = (fn () => $this->getData())->call($widget);

    expect($data['labels'])->toBe(['Bypass', 'Sellos Rotos'])
        ->and($data['datasets'][0]['data'])->toBe([2, 1]);
});

test('ordenes por proceso chart only counts ordenes matching the selected criterio', function () {
    $procesoA = Proceso::create(['nombre' => 'Proceso A']);
    $procesoB = Proceso::create(['nombre' => 'Proceso B']);

    OrdenJob::factory()->create(['proceso_id' => $procesoA->id, 'estado' => EstadoOrden::Completada]);
    OrdenJob::factory()->create(['proceso_id' => $procesoA->id, 'estado' => EstadoOrden::Pendiente]);
    OrdenJob::factory()->create(['proceso_id' => $procesoB->id, 'estado' => EstadoOrden::Completada]);

    $widget = new OrdenesPorProcesoChart;
    $widget->estado = EstadoOrden::Completada->value;

    $data = (fn () => $this->getData())->call($widget);

    expect($data['labels'])->toBe(['Proceso A', 'Proceso B'])
        ->and($data['datasets'][0]['data'])->toBe([1, 1]);
});
