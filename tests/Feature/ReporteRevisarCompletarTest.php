<?php

use App\Enums\EstadoOrden;
use App\Enums\TipoServicio;
use App\Livewire\Coordinador\ReporteRevisar;
use App\Models\auditoria;
use App\Models\Company;
use App\Models\OrdenJob;
use App\Models\personals;
use App\Models\reportes;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function crearOrdenEnRevision(Company $company): array
{
    $personal = personals::factory()->create(['company_id' => $company->id]);
    $orden = OrdenJob::factory()->create([
        'tipo_servicio' => TipoServicio::RevisionMedidores->value,
        'company_id' => $company->id,
        'personals_id' => $personal->id,
        'estado' => EstadoOrden::EnRevision->value,
    ]);
    $reporte = reportes::factory()->create([
        'orden_job_id' => $orden->id,
        'personals_id' => $personal->id,
        'numero_orden' => $orden->numero_orden,
    ]);

    return [$orden, $reporte];
}

it('rechaza completar la orden con resultado pendiente', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $this->actingAs($user);

    [$orden, $reporte] = crearOrdenEnRevision($company);

    Livewire::test(ReporteRevisar::class, ['orden' => $orden])
        ->set('conclusion', 'Conclusión de prueba con suficiente longitud.')
        ->set('resultado', 'pendiente')
        ->call('completar')
        ->assertHasErrors(['resultado']);

    expect($orden->fresh()->estado)->toBe(EstadoOrden::EnRevision)
        ->and(auditoria::where('reportes_id', $reporte->id)->exists())->toBeFalse();
});

it('permite completar la orden con resultado positivo o negativo', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $this->actingAs($user);

    [$orden, $reporte] = crearOrdenEnRevision($company);

    Livewire::test(ReporteRevisar::class, ['orden' => $orden])
        ->set('conclusion', 'Conclusión de prueba con suficiente longitud.')
        ->set('resultado', 'negativo')
        ->call('completar')
        ->assertHasNoErrors();

    expect($orden->fresh()->estado)->toBe(EstadoOrden::Completada)
        ->and(auditoria::where('reportes_id', $reporte->id)->exists())->toBeFalse();
});
