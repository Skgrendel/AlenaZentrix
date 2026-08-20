<?php

use App\Enums\EstadoAuditoria;
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

function ordenConAuditoriaConfirmadaSinMetrosCubicos(): array
{
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $personal = personals::factory()->create(['company_id' => $company->id]);

    $orden = OrdenJob::factory()->create([
        'tipo_servicio' => TipoServicio::Fraudes->value,
        'company_id' => $company->id,
        'personals_id' => $personal->id,
        'estado' => EstadoOrden::Completada->value,
        'resultado' => 'positivo',
    ]);

    $reporte = reportes::factory()->create([
        'orden_job_id' => $orden->id,
        'personals_id' => $personal->id,
        'numero_orden' => $orden->numero_orden,
    ]);

    auditoria::create([
        'reportes_id' => $reporte->id,
        'estado' => EstadoAuditoria::Confirmado->value,
        'evidencias' => [],
        'fecha_auditoria' => now(),
        'metros_cubicos_recuperados' => null,
    ]);

    return [$user, $orden];
}

it('muestra el boton para agregar M³ recuperados en Anexos de la Orden cuando el dato esta vacio', function () {
    [$user, $orden] = ordenConAuditoriaConfirmadaSinMetrosCubicos();
    $this->actingAs($user);

    Livewire::test(ReporteRevisar::class, ['orden' => $orden])
        ->assertSee('Agregar M³ Recuperados');
});

it('valida que el valor sea requerido al guardar los M³ recuperados desde Anexos de la Orden', function () {
    [$user, $orden] = ordenConAuditoriaConfirmadaSinMetrosCubicos();
    $this->actingAs($user);

    Livewire::test(ReporteRevisar::class, ['orden' => $orden])
        ->set('metrosCubicosRecuperados', null)
        ->call('guardarMetrosCubicosRecuperados')
        ->assertHasErrors(['metrosCubicosRecuperados' => 'required']);

    expect($orden->fresh()->reportes->first()->auditoria->metros_cubicos_recuperados)->toBeNull();
});

it('permite completar los M³ recuperados desde Anexos de la Orden', function () {
    [$user, $orden] = ordenConAuditoriaConfirmadaSinMetrosCubicos();
    $this->actingAs($user);

    Livewire::test(ReporteRevisar::class, ['orden' => $orden])
        ->set('metrosCubicosRecuperados', 50)
        ->call('guardarMetrosCubicosRecuperados')
        ->assertHasNoErrors()
        ->assertDontSee('Agregar M³ Recuperados');

    expect($orden->fresh()->reportes->first()->auditoria->metros_cubicos_recuperados)->toBe('50');
});
