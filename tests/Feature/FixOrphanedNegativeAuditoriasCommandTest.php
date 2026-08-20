<?php

use App\Enums\EstadoAuditoria;
use App\Enums\EstadoOrden;
use App\Enums\TipoServicio;
use App\Models\auditoria;
use App\Models\Company;
use App\Models\OrdenJob;
use App\Models\personals;
use App\Models\reportes;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function crearAuditoriaHuerfana(string $resultadoOrden): auditoria
{
    $company = Company::factory()->create();
    $personal = personals::factory()->create(['company_id' => $company->id]);
    $orden = OrdenJob::factory()->create([
        'tipo_servicio' => TipoServicio::RevisionMedidores->value,
        'company_id' => $company->id,
        'personals_id' => $personal->id,
        'estado' => EstadoOrden::Completada->value,
        'resultado' => $resultadoOrden,
    ]);
    $reporte = reportes::factory()->create([
        'orden_job_id' => $orden->id,
        'personals_id' => $personal->id,
        'numero_orden' => $orden->numero_orden,
    ]);

    return auditoria::create([
        'reportes_id' => $reporte->id,
        'estado' => EstadoAuditoria::PendienteAuditoria,
    ]);
}

it('sin --force solo lista las auditorias huerfanas sin modificarlas', function () {
    $auditoria = crearAuditoriaHuerfana('negativo');

    $this->artisan('app:fix-orphaned-negative-auditorias')
        ->assertSuccessful();

    expect($auditoria->fresh()->estado)->toBe(EstadoAuditoria::PendienteAuditoria);
});

it('con --force marca como Negativo las auditorias de ordenes con resultado negativo', function () {
    $auditoria = crearAuditoriaHuerfana('negativo');

    $this->artisan('app:fix-orphaned-negative-auditorias', ['--force' => true])
        ->assertSuccessful();

    expect($auditoria->fresh()->estado)->toBe(EstadoAuditoria::Negativo);
});

it('con --force tambien marca auditorias que ya tienen evidencias u observaciones cargadas', function () {
    $auditoria = crearAuditoriaHuerfana('negativo');
    $auditoria->update(['observaciones' => 'Alguien empezó a revisar esto manualmente.']);

    $this->artisan('app:fix-orphaned-negative-auditorias', ['--force' => true])
        ->assertSuccessful();

    expect($auditoria->fresh()->estado)->toBe(EstadoAuditoria::Negativo);
});

it('no toca auditorias de ordenes con resultado positivo', function () {
    $auditoria = crearAuditoriaHuerfana('positivo');

    $this->artisan('app:fix-orphaned-negative-auditorias', ['--force' => true])
        ->assertSuccessful();

    expect($auditoria->fresh()->estado)->toBe(EstadoAuditoria::PendienteAuditoria);
});

it('no toca auditorias que ya fueron confirmadas o marcadas como negativas', function () {
    $auditoria = crearAuditoriaHuerfana('negativo');
    $auditoria->update(['estado' => EstadoAuditoria::Confirmado]);

    $this->artisan('app:fix-orphaned-negative-auditorias', ['--force' => true])
        ->assertSuccessful();

    expect($auditoria->fresh()->estado)->toBe(EstadoAuditoria::Confirmado);
});
