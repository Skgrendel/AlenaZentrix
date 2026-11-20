<?php

use App\Enums\EstadoOrden;
use App\Enums\EstadoSurveyRegistro;
use App\Enums\TipoServicio;
use App\Livewire\Coordinador\SurveyAuditoria\SurveyRegistrosIndex;
use App\Models\Company;
use App\Models\OrdenJob;
use App\Models\personals;
use App\Models\SurveyRegistro;
use App\Models\User;
use App\Services\coordinador\AsignarAuditoriaSurveyService;
use App\Services\coordinador\DataGisServices;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// Estas pruebas no deben llamar al portal real del GIS.
beforeEach(function () {
    $this->mock(DataGisServices::class, function ($mock): void {
        $mock->shouldReceive('consultarContrato')->andReturn(['error' => 'Servicio GIS no disponible']);
    });
});

function coordinadorConRegistro(): array
{
    test()->seed(PermissionsSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('Coordinador');

    $registro = SurveyRegistro::factory()->create([
        'company_id' => $user->company_id,
        'orden_trabajo' => '319890863',
        'contrato' => '296192',
        'lectura_medidor' => '6972',
        'serie_medidor' => '981797',
        'latitud' => 10.407333,
        'longitud' => -75.488048,
    ]);

    $auditor = personals::factory()->create(['company_id' => $user->company_id]);

    return [$user, $registro, $auditor];
}

test('asignar crea la orden de auditoría con los datos reportados como referencia', function () {
    [, $registro, $auditor] = coordinadorConRegistro();

    $orden = app(AsignarAuditoriaSurveyService::class)->asignar($registro, $auditor->id);

    expect($orden->tipo_servicio)->toBe(TipoServicio::AuditoriaCampo)
        ->and($orden->tipo_auditoria_campo)->toBe('visita_calidad_suspensiones')
        ->and($orden->estado)->toBe(EstadoOrden::Asignada)
        ->and($orden->personals_id)->toBe($auditor->id)
        ->and($orden->survey_registro_id)->toBe($registro->id)
        ->and($orden->contrato)->toBe('296192')
        ->and($orden->tecnico)->toBe('Luis Quiroga')
        ->and($orden->lectura_referencia)->toBe('6972')
        ->and($orden->serie_medidor_referencia)->toBe('981797')
        ->and((float) $orden->latitud_referencia)->toBe(10.407333)
        ->and((float) $orden->longitud_referencia)->toBe(-75.488048)
        ->and($orden->comentario_usuario)->toBeNull()
        ->and($orden->surveyRegistro->orden_trabajo)->toBe('319890863')
        ->and($registro->fresh()->estado)->toBe(EstadoSurveyRegistro::Asignado);
});

test('el plazo por defecto de la visita es de 48 horas', function () {
    [, $registro, $auditor] = coordinadorConRegistro();

    $orden = app(AsignarAuditoriaSurveyService::class)->asignar($registro, $auditor->id);

    expect($orden->fecha_limite->diffInHours(now(), true))->toBeGreaterThan(47)->toBeLessThanOrEqual(48);
});

test('no se puede asignar dos veces el mismo registro', function () {
    [, $registro, $auditor] = coordinadorConRegistro();
    $servicio = app(AsignarAuditoriaSurveyService::class);

    $servicio->asignar($registro, $auditor->id);

    expect(fn () => $servicio->asignar($registro, $auditor->id))
        ->toThrow(DomainException::class, 'ya tiene una auditoría de campo asignada');

    expect(OrdenJob::query()->where('survey_registro_id', $registro->id)->count())->toBe(1);
});

test('un registro con la orden cancelada se puede volver a asignar', function () {
    [, $registro, $auditor] = coordinadorConRegistro();
    $servicio = app(AsignarAuditoriaSurveyService::class);

    $primera = $servicio->asignar($registro, $auditor->id);
    $primera->update(['estado' => EstadoOrden::Cancelada]);

    $segunda = $servicio->asignar($registro, $auditor->id);

    expect($segunda->id)->not->toBe($primera->id);
});

test('no se puede asignar a un auditor de otra empresa', function () {
    [, $registro] = coordinadorConRegistro();
    $ajeno = personals::factory()->create(['company_id' => Company::factory()->create()->id]);

    expect(fn () => app(AsignarAuditoriaSurveyService::class)->asignar($registro, $ajeno->id))
        ->toThrow(DomainException::class, 'no pertenece a la empresa');

    expect($registro->fresh()->estado)->toBe(EstadoSurveyRegistro::Pendiente);
});

test('la acción Asignar de la tabla crea la orden', function () {
    [$user, $registro, $auditor] = coordinadorConRegistro();

    Livewire::actingAs($user)
        ->test(SurveyRegistrosIndex::class)
        ->assertCanSeeTableRecords([$registro])
        ->callTableAction('asignar', $registro, [
            'personals_id' => $auditor->id,
            'fecha_limite' => now()->addHours(24)->toDateTimeString(),
        ])
        ->assertHasNoTableActionErrors();

    expect(OrdenJob::query()->where('survey_registro_id', $registro->id)->count())->toBe(1)
        ->and($registro->fresh()->estado)->toBe(EstadoSurveyRegistro::Asignado);
});

test('la acción Asignar no aparece para un registro ya asignado', function () {
    [$user, $registro, $auditor] = coordinadorConRegistro();
    app(AsignarAuditoriaSurveyService::class)->asignar($registro, $auditor->id);

    Livewire::actingAs($user)
        ->test(SurveyRegistrosIndex::class)
        ->assertTableActionHidden('asignar', $registro->fresh())
        ->assertTableActionVisible('ver_orden', $registro->fresh());
});

test('un usuario sin permiso de órdenes no entra a la pantalla', function () {
    $this->seed(PermissionsSeeder::class);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('operaciones.survey-auditoria.index'))
        ->assertForbidden();
});

test('un coordinador entra a la pantalla de auditoría de suspensiones', function () {
    [$user] = coordinadorConRegistro();

    $this->actingAs($user)
        ->get(route('operaciones.survey-auditoria.index'))
        ->assertOk()
        ->assertSee('Auditoría de Suspensiones');
});

test('la lista enlaza al detalle de cada registro', function () {
    [$user, $registro] = coordinadorConRegistro();

    Livewire::actingAs($user)
        ->test(SurveyRegistrosIndex::class)
        ->assertTableActionVisible('ver_detalle', $registro)
        ->assertTableActionHasUrl('ver_detalle', route('operaciones.survey-auditoria.show', $registro), $registro);
});
