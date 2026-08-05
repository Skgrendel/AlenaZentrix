<?php

use App\Enums\EstadoOrden;
use App\Enums\TipoServicio;
use App\Livewire\Coordinador\ReportesVigilanciaIndex;
use App\Models\Company;
use App\Models\OrdenJob;
use App\Models\personals;
use App\Models\reportes;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('rechaza la orden completa sin marcar ningun reporte individual', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $this->actingAs($user);

    $personal = personals::factory()->create(['company_id' => $company->id]);
    $orden = OrdenJob::factory()->create([
        'tipo_servicio' => TipoServicio::Vigilancia->value,
        'company_id' => $company->id,
        'personals_id' => $personal->id,
        'estado' => EstadoOrden::EnRevision->value,
    ]);

    $turno1 = reportes::factory()->create(['orden_job_id' => $orden->id, 'personals_id' => $personal->id, 'numero_orden' => $orden->numero_orden]);
    $turno2 = reportes::factory()->create(['orden_job_id' => $orden->id, 'personals_id' => $personal->id, 'numero_orden' => $orden->numero_orden]);

    Livewire::test(ReportesVigilanciaIndex::class, ['orden' => $orden])
        ->set('comentarios', 'Rechazo de todo el conjunto de turnos por inconsistencias.')
        ->call('rechazar')
        ->assertHasNoErrors();

    expect($orden->fresh()->estado)->toBe(EstadoOrden::Rechazada)
        ->and($turno1->fresh()->requiere_correccion)->toBeFalse()
        ->and($turno2->fresh()->requiere_correccion)->toBeFalse();
});
