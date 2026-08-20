<?php

use App\Enums\EstadoOrden;
use App\Enums\TipoServicio;
use App\Livewire\Coordinador\ReporteRevisar;
use App\Models\Company;
use App\Models\OrdenJob;
use App\Models\personals;
use App\Models\reportes;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function ordenCompletadaSinFechaEntrega(): array
{
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $personal = personals::factory()->create(['company_id' => $company->id]);

    $orden = OrdenJob::factory()->create([
        'tipo_servicio' => TipoServicio::Fraudes->value,
        'company_id' => $company->id,
        'personals_id' => $personal->id,
        'estado' => EstadoOrden::Completada->value,
        'fecha_entrega' => null,
    ]);

    reportes::factory()->create([
        'orden_job_id' => $orden->id,
        'personals_id' => $personal->id,
        'numero_orden' => $orden->numero_orden,
    ]);

    return [$user, $orden];
}

it('muestra el boton para agregar la fecha de entrega cuando esta vacia', function () {
    [$user, $orden] = ordenCompletadaSinFechaEntrega();
    $this->actingAs($user);

    Livewire::test(ReporteRevisar::class, ['orden' => $orden])
        ->assertSee('Agregar Fecha de Entrega');
});

it('valida que la fecha de entrega sea requerida al guardar', function () {
    [$user, $orden] = ordenCompletadaSinFechaEntrega();
    $this->actingAs($user);

    Livewire::test(ReporteRevisar::class, ['orden' => $orden])
        ->set('fechaEntrega', null)
        ->call('guardarFechaEntrega')
        ->assertHasErrors(['fechaEntrega' => 'required']);

    expect($orden->fresh()->fecha_entrega)->toBeNull();
});

it('permite registrar la fecha de entrega desde Anexos de la Orden', function () {
    [$user, $orden] = ordenCompletadaSinFechaEntrega();
    $this->actingAs($user);

    Livewire::test(ReporteRevisar::class, ['orden' => $orden])
        ->set('fechaEntrega', '2026-08-20T10:30')
        ->call('guardarFechaEntrega')
        ->assertHasNoErrors()
        ->assertDontSee('Agregar Fecha de Entrega');

    expect($orden->fresh()->fecha_entrega?->format('Y-m-d H:i'))->toBe('2026-08-20 10:30');
});
