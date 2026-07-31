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

it('marca solo el reporte especifico como requiere_correccion al rechazar sin bloquear la orden', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $this->actingAs($user);

    $personal = personals::factory()->create(['company_id' => $company->id]);
    $orden = OrdenJob::factory()->create([
        'tipo_servicio' => TipoServicio::Vigilancia->value,
        'company_id' => $company->id,
        'personals_id' => $personal->id,
        'estado' => EstadoOrden::EnProgreso->value,
    ]);

    $turno1 = reportes::factory()->create([
        'orden_job_id' => $orden->id,
        'personals_id' => $personal->id,
        'numero_orden' => $orden->numero_orden,
        'created_at' => now()->subDays(3),
    ]);
    $turno2 = reportes::factory()->create([
        'orden_job_id' => $orden->id,
        'personals_id' => $personal->id,
        'numero_orden' => $orden->numero_orden,
        'created_at' => now()->subDay(),
    ]);

    Livewire::withQueryParams(['reporte' => $turno1->id])
        ->test(ReporteRevisar::class, ['orden' => $orden])
        ->set('comentarios', 'El turno 1 no reporta la hora de salida correcta.')
        ->call('rechazar')
        ->assertHasNoErrors();

    // La orden permanece activa: el rechazo es puntual a este turno y no bloquea
    // el resto de la orden de Vigilancia ni impide seguir agregando reportes.
    expect($orden->fresh()->estado)->toBe(EstadoOrden::EnProgreso)
        ->and($turno1->fresh()->requiere_correccion)->toBeTrue()
        ->and($turno1->fresh()->comentarios)->toBe('El turno 1 no reporta la hora de salida correcta.')
        ->and($turno2->fresh()->requiere_correccion)->toBeFalse();
});

it('rechazar un reporte de un servicio de un solo reporte si bloquea toda la orden', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $this->actingAs($user);

    $personal = personals::factory()->create(['company_id' => $company->id]);
    $orden = OrdenJob::factory()->create([
        'tipo_servicio' => TipoServicio::Investigacion->value,
        'company_id' => $company->id,
        'personals_id' => $personal->id,
        'estado' => EstadoOrden::EnRevision->value,
    ]);

    $reporte = reportes::factory()->create([
        'orden_job_id' => $orden->id,
        'personals_id' => $personal->id,
        'numero_orden' => $orden->numero_orden,
    ]);

    Livewire::test(ReporteRevisar::class, ['orden' => $orden])
        ->set('comentarios', 'Falta soporte fotográfico de la diligencia.')
        ->call('rechazar')
        ->assertHasNoErrors();

    expect($orden->fresh()->estado)->toBe(EstadoOrden::Rechazada)
        ->and($reporte->fresh()->requiere_correccion)->toBeTrue();
});
