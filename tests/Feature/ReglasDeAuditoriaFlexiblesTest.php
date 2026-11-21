<?php

use App\Exports\SurveyAuditoria\HojaAuditoriaExport;
use App\Jobs\ReauditarRegistrosJob;
use App\Livewire\Admin\ConfiguracionReglasAuditoria;
use App\Models\AjusteAuditoria;
use App\Models\Company;
use App\Models\SurveyRegistro;
use App\Models\User;
use App\Services\survey123\AuditoriaSurveyRegistro;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function registroSinTecnico(Company $empresa, array $extra = []): SurveyRegistro
{
    return SurveyRegistro::factory()->create($extra + ['company_id' => $empresa->id, 'tecnico' => null]);
}

/** @return array<int, array<string, string>> */
function hallazgosDeLaRegla(SurveyRegistro $registro, string $regla): array
{
    return collect((new AuditoriaSurveyRegistro)->auditar($registro))->where('regla', $regla)->values()->all();
}

function administradorDeReglas(?Company $empresa = null): User
{
    test()->seed(PermissionsSeeder::class);
    $usuario = User::factory()->create(['company_id' => ($empresa ?? Company::factory()->create())->id]);
    $usuario->assignRole('Administrador');

    return $usuario;
}

// ── Qué tan grave es cada regla ──────────────────────────────────────────────

test('sin configuración se usan los valores sugeridos: las fotos, la justificación y las series de sonda y caps se corrigen', function () {
    $pesos = AjusteAuditoria::pesosDe(Company::factory()->create()->id);

    expect($pesos['fotos_minimas'])->toBe('corregible')
        ->and($pesos['fotos_repetidas'])->toBe('corregible')
        ->and($pesos['justificacion'])->toBe('corregible')
        ->and($pesos['serie_sonda_caps'])->toBe('corregible')
        ->and($pesos['lectura'])->toBe('segun_el_caso')
        ->and($pesos['contrato'])->toBe('segun_el_caso')
        ->and($pesos)->toHaveCount(count(AjusteAuditoria::REGLAS));
});

test('lo que la empresa guarda manda sobre lo sugerido, y no afecta a otras empresas', function () {
    $a = Company::factory()->create();
    $b = Company::factory()->create();
    AjusteAuditoria::query()->create(['company_id' => $a->id, 'pesos' => ['fotos_minimas' => 'bloqueante', 'lectura' => 'no_aplicar']]);

    expect(AjusteAuditoria::pesosDe($a->id)['fotos_minimas'])->toBe('bloqueante')
        ->and(AjusteAuditoria::pesosDe($a->id)['lectura'])->toBe('no_aplicar')
        ->and(AjusteAuditoria::pesosDe($a->id)['fotos_repetidas'])->toBe('corregible') // lo no guardado sigue sugerido
        ->and(AjusteAuditoria::pesosDe($b->id)['fotos_minimas'])->toBe('corregible')
        ->and(AjusteAuditoria::pesosDe(null)['fotos_minimas'])->toBe('corregible');
});

test('al auditar, cada nivel cambia la gravedad del hallazgo: según el caso, bloqueante, corregible o no aplicar', function () {
    $empresa = Company::factory()->create();
    $sinTecnico = registroSinTecnico($empresa);                                          // falta el técnico: error según el caso
    $sinApellido = SurveyRegistro::factory()->create(['company_id' => $empresa->id, 'tecnico' => 'Luis']); // sin apellido: alerta según el caso

    expect(hallazgosDeLaRegla($sinTecnico, 'tecnico')[0]['severidad'])->toBe('error')
        ->and(hallazgosDeLaRegla($sinApellido, 'tecnico')[0]['severidad'])->toBe('alerta');

    AjusteAuditoria::query()->create(['company_id' => $empresa->id, 'pesos' => ['tecnico' => 'corregible']]);
    expect(hallazgosDeLaRegla($sinTecnico, 'tecnico')[0]['severidad'])->toBe('alerta');

    AjusteAuditoria::query()->where('company_id', $empresa->id)->update(['pesos' => ['tecnico' => 'bloqueante']]);
    expect(hallazgosDeLaRegla($sinApellido, 'tecnico')[0]['severidad'])->toBe('error');

    AjusteAuditoria::query()->where('company_id', $empresa->id)->update(['pesos' => ['tecnico' => 'no_aplicar']]);
    expect(hallazgosDeLaRegla($sinTecnico, 'tecnico'))->toBe([])
        ->and(hallazgosDeLaRegla($sinApellido, 'tecnico'))->toBe([]);
});

test('lo que configura una empresa no cambia la auditoría de otra, aunque se audite con la misma instancia', function () {
    $a = Company::factory()->create();
    $b = Company::factory()->create();
    AjusteAuditoria::query()->create(['company_id' => $a->id, 'pesos' => ['tecnico' => 'corregible']]);
    $auditor = new AuditoriaSurveyRegistro;

    $deA = collect($auditor->auditar(registroSinTecnico($a)))->firstWhere('regla', 'tecnico');
    $deB = collect($auditor->auditar(registroSinTecnico($b)))->firstWhere('regla', 'tecnico');
    $otraVezA = collect($auditor->auditar(registroSinTecnico($a)))->firstWhere('regla', 'tecnico');

    expect($deA['severidad'])->toBe('alerta')->and($deB['severidad'])->toBe('error')->and($otraVezA['severidad'])->toBe('alerta');
});

test('los totales y la conclusión propuesta siguen la gravedad configurada: sin fotos ya no es «No se aprueba»', function () {
    $empresa = Company::factory()->create();
    $registro = SurveyRegistro::factory()->create(['company_id' => $empresa->id, 'justificacion' => 'Se realiza suspensión desde acometida']);

    (new AuditoriaSurveyRegistro)->auditarYGuardar($registro);
    $registro = $registro->fresh();
    $fotosFaltantes = collect($registro->hallazgos)->where('regla', 'fotos_minimas');

    // Sin fotos: con los valores sugeridos es corregible (alerta) y propone pedir correcciones.
    expect($fotosFaltantes)->not->toBeEmpty()
        ->and($fotosFaltantes->every(fn ($h) => $h['severidad'] === 'alerta'))->toBeTrue()
        ->and($registro->total_alertas)->toBeGreaterThan(0)
        ->and((new HojaAuditoriaExport(SurveyRegistro::query()))->map($registro->load('ultimaRevisionIA'))[array_key_last((new HojaAuditoriaExport(SurveyRegistro::query()))->headings())])
        ->toBe('Se aprueba solicitando correcciones');

    // Si la empresa decide que las fotos sí bloquean, vuelve a «No se aprueba».
    AjusteAuditoria::query()->create(['company_id' => $empresa->id, 'pesos' => ['fotos_minimas' => 'bloqueante']]);
    (new AuditoriaSurveyRegistro)->auditarYGuardar($registro);
    $registro = $registro->fresh()->load('ultimaRevisionIA');

    expect(collect($registro->hallazgos)->where('regla', 'fotos_minimas')->every(fn ($h) => $h['severidad'] === 'error'))->toBeTrue()
        ->and($registro->total_errores)->toBeGreaterThan(0)
        ->and((new HojaAuditoriaExport(SurveyRegistro::query()))->map($registro)[array_key_last((new HojaAuditoriaExport(SurveyRegistro::query()))->headings())])
        ->toBe('No se aprueba');
});

// ── Volver a auditar lo ya cargado ───────────────────────────────────────────

test('el trabajo de re-auditar actualiza los hallazgos de la empresa y deja intacto lo demás', function () {
    $a = Company::factory()->create();
    $b = Company::factory()->create();
    $deA = registroSinTecnico($a);
    $deB = registroSinTecnico($b);
    (new AuditoriaSurveyRegistro)->auditarYGuardar($deA);
    (new AuditoriaSurveyRegistro)->auditarYGuardar($deB);
    $estadoAntes = $deA->fresh()->estado;

    AjusteAuditoria::query()->create(['company_id' => $a->id, 'pesos' => ['tecnico' => 'corregible']]);
    ReauditarRegistrosJob::dispatchSync($a->id);

    $tecnicoDeA = collect($deA->fresh()->hallazgos)->firstWhere('regla', 'tecnico');
    $tecnicoDeB = collect($deB->fresh()->hallazgos)->firstWhere('regla', 'tecnico');

    expect($tecnicoDeA['severidad'])->toBe('alerta')
        ->and($tecnicoDeB['severidad'])->toBe('error')
        ->and($deA->fresh()->estado)->toBe($estadoAntes);
});

// ── Pantalla de Configuración ────────────────────────────────────────────────

test('la pantalla muestra las 15 reglas con su nivel y marca las sugeridas', function () {
    $admin = administradorDeReglas();

    $pantalla = Livewire::actingAs($admin)->test(ConfiguracionReglasAuditoria::class);

    foreach (AjusteAuditoria::REGLAS as $codigo => $regla) {
        $pantalla->assertSee($regla['nombre'])->assertSet("niveles.{$codigo}", AjusteAuditoria::pesosDe($admin->company_id)[$codigo]);
    }

    $pantalla->assertSee('sugerido: Corregible');
    expect(AjusteAuditoria::REGLAS)->toHaveCount(15);
});

test('guardar deja los niveles de la empresa y vuelve a auditar sus registros', function () {
    Queue::fake();
    $admin = administradorDeReglas();

    Livewire::actingAs($admin)->test(ConfiguracionReglasAuditoria::class)
        ->set('niveles.fotos_minimas', 'bloqueante')
        ->set('niveles.lectura', 'corregible')
        ->call('guardar')->assertHasNoErrors();

    $guardado = AjusteAuditoria::pesosDe($admin->company_id);

    expect($guardado['fotos_minimas'])->toBe('bloqueante')->and($guardado['lectura'])->toBe('corregible')
        ->and(AjusteAuditoria::query()->count())->toBe(1);

    Queue::assertPushed(ReauditarRegistrosJob::class, fn (ReauditarRegistrosJob $job) => $job->companyId === $admin->company_id);
});

test('no se aceptan niveles inventados', function () {
    Queue::fake();
    $admin = administradorDeReglas();

    Livewire::actingAs($admin)->test(ConfiguracionReglasAuditoria::class)
        ->set('niveles.fotos_minimas', 'muy_grave')->call('guardar')->assertHasErrors(['niveles.fotos_minimas']);

    expect(AjusteAuditoria::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

test('guardar cambia solo la configuración de la propia empresa', function () {
    Queue::fake();
    $admin = administradorDeReglas();
    $ajena = Company::factory()->create();
    AjusteAuditoria::query()->create(['company_id' => $ajena->id, 'pesos' => ['fotos_minimas' => 'no_aplicar']]);

    Livewire::actingAs($admin)->test(ConfiguracionReglasAuditoria::class)->set('niveles.fotos_minimas', 'bloqueante')->call('guardar');

    expect(AjusteAuditoria::pesosDe($ajena->id)['fotos_minimas'])->toBe('no_aplicar')
        ->and(AjusteAuditoria::query()->count())->toBe(2);
});

test('«Volver a los valores sugeridos» deja en pantalla lo sugerido sin guardar nada', function () {
    $admin = administradorDeReglas();
    AjusteAuditoria::query()->create(['company_id' => $admin->company_id, 'pesos' => ['fotos_minimas' => 'bloqueante']]);

    Livewire::actingAs($admin)->test(ConfiguracionReglasAuditoria::class)
        ->assertSet('niveles.fotos_minimas', 'bloqueante')
        ->call('restablecerSugeridos')
        ->assertSet('niveles.fotos_minimas', 'corregible')
        ->assertSet('niveles.lectura', 'segun_el_caso');

    expect(AjusteAuditoria::pesosDe($admin->company_id)['fotos_minimas'])->toBe('bloqueante');
});

test('quien no tiene el permiso de configuración no entra, y la pestaña aparece para quien sí', function () {
    test()->seed(PermissionsSeeder::class);
    $coordinador = User::factory()->create();
    $coordinador->assignRole('Coordinador');
    Livewire::actingAs($coordinador)->test(ConfiguracionReglasAuditoria::class)->assertForbidden();

    $clase = Str::studly('configuracion-reglas-auditoria');
    expect(scandir(app_path('Livewire/Admin')))->toContain($clase.'.php');

    $this->actingAs(administradorDeReglas())->get(route('admin.configuracion'))
        ->assertOk()->assertSee('Reglas de la auditoría')->assertSeeLivewire('admin.configuracion-reglas-auditoria');
});
