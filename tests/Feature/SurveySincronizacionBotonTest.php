<?php

use App\Jobs\SincronizarSurvey123Job;
use App\Livewire\Coordinador\SurveyAuditoria\SurveyRegistrosIndex;
use App\Models\Company;
use App\Models\SurveyRegistro;
use App\Models\User;
use App\Services\survey123\EstadoSincronizacion;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();

    config(['services.survey123' => [
        'usuario' => 'usuario', 'password' => 'clave',
        'portal_url' => 'https://portal.test', 'service_url' => 'https://services.test/FeatureServer/0',
        'disco_fotos' => 's3', 'espera_sincronizacion_minutos' => 10,
    ]]);
});

afterEach(fn () => Carbon::setTestNow());

function simularSurveyParaElJob(array $features = []): void
{
    Http::fake([
        '*/sharing/rest/generateToken' => Http::response(['token' => 'tok']),
        'https://services.test/FeatureServer/0' => Http::response([
            'objectIdField' => 'objectid', 'globalIdField' => 'globalid',
            'editFieldsInfo' => ['editDateField' => 'EditDate', 'creationDateField' => 'CreationDate'],
            'fields' => [],
        ]),
        'https://services.test/FeatureServer/0/query' => Http::response(['features' => $features]),
        'https://services.test/FeatureServer/0/queryAttachments' => Http::response(['attachmentGroups' => []]),
    ]);
}

function registroDeSurvey(int $id): array
{
    return [
        'attributes' => ['objectid' => $id, 'globalid' => "g-{$id}", 'EditDate' => 1_763_600_000_000, 'CreationDate' => 1_763_500_000_000],
        'geometry' => ['x' => -75.48, 'y' => 10.40],
    ];
}

/** @return array{0: User, 1: string} */
function coordinadorParaSincronizar(): array
{
    test()->seed(PermissionsSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('Coordinador');

    return [$user, $user->company_id];
}

// ── Estado y tiempo de espera ────────────────────────────────────────────────

test('iniciar deja la sincronización en cola', function () {
    $empresa = Company::factory()->create()->id;

    expect(app(EstadoSincronizacion::class)->iniciar($empresa, 'Ana'))->toBeNull();

    $estado = app(EstadoSincronizacion::class)->estado($empresa);
    expect($estado['estado'])->toBe('en_cola')->and($estado['usuario'])->toBe('Ana');
});

test('no se puede iniciar otra mientras hay una en curso', function () {
    $empresa = Company::factory()->create()->id;
    $servicio = app(EstadoSincronizacion::class);

    $servicio->iniciar($empresa);

    expect($servicio->iniciar($empresa))->toBe('Ya hay una sincronización en curso.')
        ->and($servicio->enCurso($empresa))->toBeTrue();
});

test('después de terminar hay que esperar el tiempo configurado', function () {
    Carbon::setTestNow('2026-11-20 10:00:00');
    $empresa = Company::factory()->create()->id;
    $servicio = app(EstadoSincronizacion::class);

    $servicio->iniciar($empresa);
    $servicio->terminar($empresa, ['nuevos' => 1, 'actualizados' => 0, 'sin_cambios' => 0, 'fotos' => 0, 'errores' => 0]);

    expect($servicio->enCurso($empresa))->toBeFalse()
        ->and($servicio->segundosDeEspera($empresa))->toBe(600)
        ->and($servicio->iniciar($empresa))->toBe('Podrás volver a sincronizar en 10 min.');

    Carbon::setTestNow('2026-11-20 10:04:00');
    expect($servicio->iniciar($empresa))->toBe('Podrás volver a sincronizar en 6 min.');

    Carbon::setTestNow('2026-11-20 10:10:01');
    expect($servicio->segundosDeEspera($empresa))->toBe(0)
        ->and($servicio->iniciar($empresa))->toBeNull();
});

test('la espera también aplica después de un fallo', function () {
    $empresa = Company::factory()->create()->id;
    $servicio = app(EstadoSincronizacion::class);

    $servicio->iniciar($empresa);
    $servicio->fallar($empresa, 'sin conexión');

    expect($servicio->iniciar($empresa))->toStartWith('Podrás volver a sincronizar en');
});

test('una sincronización que lleva más de 30 minutos sin terminar se considera caída', function () {
    Carbon::setTestNow('2026-11-20 10:00:00');
    $empresa = Company::factory()->create()->id;
    $servicio = app(EstadoSincronizacion::class);

    $servicio->iniciar($empresa);
    Carbon::setTestNow('2026-11-20 10:31:00');

    expect($servicio->enCurso($empresa))->toBeFalse()
        ->and($servicio->iniciar($empresa))->toBeNull();
});

test('cada empresa tiene su propio estado', function () {
    [$a, $b] = [Company::factory()->create()->id, Company::factory()->create()->id];

    app(EstadoSincronizacion::class)->iniciar($a);

    expect(app(EstadoSincronizacion::class)->enCurso($b))->toBeFalse()
        ->and(app(EstadoSincronizacion::class)->iniciar($b))->toBeNull();
});

// ── Job ──────────────────────────────────────────────────────────────────────

test('el job sincroniza, informa el avance y deja el estado terminado', function () {
    Storage::fake('s3');
    $empresa = Company::factory()->create()->id;
    simularSurveyParaElJob([registroDeSurvey(1), registroDeSurvey(2)]);
    $estado = app(EstadoSincronizacion::class);
    $estado->iniciar($empresa);

    (new SincronizarSurvey123Job($empresa))->handle($estado);

    $final = $estado->estado($empresa);
    expect($final['estado'])->toBe('terminado')
        ->and($final['nuevos'])->toBe(2)
        ->and($final['fin'])->not->toBeNull()
        ->and($final['ultima_exitosa'])->toBe($final['inicio'])
        ->and(SurveyRegistro::query()->withoutGlobalScopes()->where('company_id', $empresa)->count())->toBe(2);
});

test('la segunda corrida solo pide lo editado desde un día antes de la última exitosa', function () {
    Storage::fake('s3');
    Carbon::setTestNow('2026-11-20 10:00:00');
    $empresa = Company::factory()->create()->id;
    simularSurveyParaElJob();
    $estado = app(EstadoSincronizacion::class);

    $estado->iniciar($empresa);
    (new SincronizarSurvey123Job($empresa))->handle($estado);

    Carbon::setTestNow('2026-11-20 11:00:00');
    $estado->iniciar($empresa);
    (new SincronizarSurvey123Job($empresa))->handle($estado);

    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/query') && str_contains((string) ($r->data()['where'] ?? ''), "EditDate >= timestamp '2026-11-19"));
});

test('si la sincronización falla el estado queda en error y el job lanza la excepción', function () {
    $empresa = Company::factory()->create()->id;
    Http::fake([
        '*/sharing/rest/generateToken' => Http::response(['error' => ['code' => 400, 'message' => 'Unable to generate token.']]),
        '*/sharing/rest/oauth2/authorize*' => Http::response('sin estado'),
    ]);
    $estado = app(EstadoSincronizacion::class);
    $estado->iniciar($empresa);

    expect(fn () => (new SincronizarSurvey123Job($empresa))->handle($estado))->toThrow(RuntimeException::class);

    $final = $estado->estado($empresa);
    expect($final['estado'])->toBe('error')
        ->and($final['mensaje'])->toContain('No se pudo completar la sincronización')
        ->and($final['mensaje'])->not->toContain('clave');
});

// ── Botón y franja de estado ─────────────────────────────────────────────────

test('el botón lanza la sincronización en segundo plano', function () {
    Queue::fake();
    [$user, $empresa] = coordinadorParaSincronizar();

    Livewire::actingAs($user)
        ->test(SurveyRegistrosIndex::class)
        ->callTableAction('sincronizar_survey')
        ->assertNotified('Sincronización iniciada');

    Queue::assertPushed(SincronizarSurvey123Job::class, fn ($job) => $job->companyId === $empresa);
    expect(app(EstadoSincronizacion::class)->enCurso($empresa))->toBeTrue();
});

test('el botón no lanza otra mientras hay una en curso ni durante la espera', function () {
    Queue::fake();
    [$user, $empresa] = coordinadorParaSincronizar();
    app(EstadoSincronizacion::class)->iniciar($empresa);

    Livewire::actingAs($user)
        ->test(SurveyRegistrosIndex::class)
        ->assertTableActionDisabled('sincronizar_survey');

    Queue::assertNothingPushed();
});

test('el botón queda deshabilitado durante el tiempo de espera', function () {
    [$user, $empresa] = coordinadorParaSincronizar();
    $servicio = app(EstadoSincronizacion::class);
    $servicio->iniciar($empresa);
    $servicio->terminar($empresa, ['nuevos' => 0, 'actualizados' => 0, 'sin_cambios' => 0, 'fotos' => 0, 'errores' => 0]);

    Livewire::actingAs($user)
        ->test(SurveyRegistrosIndex::class)
        ->assertTableActionDisabled('sincronizar_survey')
        ->assertSee('Podrás volver a sincronizar en');
});

test('el botón no aparece sin permiso para crear órdenes', function () {
    $this->seed(PermissionsSeeder::class);
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(SurveyRegistrosIndex::class)
        ->assertTableActionHidden('sincronizar_survey');
});

test('la franja muestra el avance mientras sincroniza y se actualiza sola', function () {
    [$user, $empresa] = coordinadorParaSincronizar();
    $servicio = app(EstadoSincronizacion::class);
    $servicio->iniciar($empresa);
    $servicio->avance($empresa, ['nuevos' => 3, 'actualizados' => 1, 'sin_cambios' => 0, 'fotos' => 12, 'errores' => 0], 'Guardando registros y fotos');

    Livewire::actingAs($user)
        ->test(SurveyRegistrosIndex::class)
        ->assertSee('Sincronizando registros…')
        ->assertSee('Guardando registros y fotos')
        ->assertSee('3 nuevos · 1 actualizados · 12 fotos')
        ->assertSeeHtml('wire:poll.3s="verificarSincronizacion"');
});

test('la franja no se actualiza sola cuando no hay sincronización en curso', function () {
    [$user, $empresa] = coordinadorParaSincronizar();
    $servicio = app(EstadoSincronizacion::class);
    $servicio->iniciar($empresa);
    $servicio->terminar($empresa, ['nuevos' => 2, 'actualizados' => 0, 'sin_cambios' => 0, 'fotos' => 5, 'errores' => 0]);

    Livewire::actingAs($user)
        ->test(SurveyRegistrosIndex::class)
        ->assertSee('Última sincronización')
        ->assertSee('2 nuevos · 0 actualizados · 5 fotos copiadas')
        ->assertDontSeeHtml('wire:poll');
});

test('al terminar se avisa una sola vez con una notificación', function () {
    [$user, $empresa] = coordinadorParaSincronizar();
    $servicio = app(EstadoSincronizacion::class);
    $servicio->iniciar($empresa);

    $componente = Livewire::actingAs($user)->test(SurveyRegistrosIndex::class);

    $componente->call('verificarSincronizacion')->assertNotNotified('Sincronización terminada');

    $servicio->terminar($empresa, ['nuevos' => 4, 'actualizados' => 0, 'sin_cambios' => 0, 'fotos' => 9, 'errores' => 0]);

    $componente->call('verificarSincronizacion')->assertNotified('Sincronización terminada');

    $avisada = $componente->get('sincronizacionAvisada');
    $componente->call('verificarSincronizacion');

    expect($avisada)->not->toBeNull()->and($componente->get('sincronizacionAvisada'))->toBe($avisada);
});

test('si la sincronización falla se avisa con una notificación de error', function () {
    [$user, $empresa] = coordinadorParaSincronizar();
    $servicio = app(EstadoSincronizacion::class);
    $servicio->iniciar($empresa);
    $servicio->fallar($empresa, 'No se pudo completar la sincronización.');

    Livewire::actingAs($user)
        ->test(SurveyRegistrosIndex::class)
        ->call('verificarSincronizacion')
        ->assertNotified('La sincronización falló')
        ->assertSee('La última sincronización falló');
});
