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

it('rechaza la orden marcando todos los turnos para correccion y la deja en progreso', function () {
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

    // La orden no pasa a Rechazada: sigue activa y cada turno queda marcado con el motivo, para que
    // el investigador corrija o agregue turnos (la API móvil no permite editar una orden Rechazada de Vigilancia).
    expect($orden->fresh()->estado)->toBe(EstadoOrden::EnProgreso)
        ->and($turno1->fresh()->requiere_correccion)->toBeTrue()
        ->and($turno2->fresh()->requiere_correccion)->toBeTrue()
        ->and($turno1->fresh()->comentarios)->toBe('Rechazo de todo el conjunto de turnos por inconsistencias.');
});
