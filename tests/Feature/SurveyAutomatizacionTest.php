<?php

use App\Jobs\SincronizarSurvey123Job;
use App\Models\Company;
use App\Services\survey123\EstadoSincronizacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    Queue::fake();

    config([
        'services.survey123.usuario' => 'usuario',
        'services.survey123.password' => 'clave',
        'services.survey123.sincronizacion_automatica' => true,
        'services.survey123.sincronizacion_cada_minutos' => 60,
        'services.survey123.empresa_id' => null,
    ]);
});

afterEach(fn () => Carbon::setTestNow());

test('el programador ejecuta la sincronización automática cada 10 minutos', function () {
    $this->artisan('schedule:list')->expectsOutputToContain('survey123:sincronizar-programado')->assertSuccessful();
});

test('lanza la sincronización en cola cuando toca', function () {
    $empresa = Company::factory()->create();

    $this->artisan('survey123:sincronizar-programado')->expectsOutputToContain('puesta en cola')->assertSuccessful();

    Queue::assertPushed(SincronizarSurvey123Job::class, fn ($job) => $job->companyId === $empresa->id);

    $estado = app(EstadoSincronizacion::class)->estado($empresa->id);
    expect($estado['estado'])->toBe('en_cola')->and($estado['usuario'])->toBe('Automática');
});

test('no hace nada si la sincronización automática está apagada', function () {
    Company::factory()->create();
    config(['services.survey123.sincronizacion_automatica' => false]);

    $this->artisan('survey123:sincronizar-programado')->assertSuccessful();

    Queue::assertNothingPushed();
});

test('se salta sola si faltan las credenciales', function (?string $usuario, ?string $password) {
    Company::factory()->create();
    config(['services.survey123.usuario' => $usuario, 'services.survey123.password' => $password]);

    $this->artisan('survey123:sincronizar-programado')->expectsOutputToContain('faltan las credenciales')->assertSuccessful();

    Queue::assertNothingPushed();
})->with([
    'sin usuario' => [null, 'clave'],
    'sin contraseña' => ['usuario', ''],
    'sin nada' => [null, null],
]);

test('no lanza otra mientras hay una en curso', function () {
    $empresa = Company::factory()->create();
    app(EstadoSincronizacion::class)->iniciar($empresa->id);

    $this->artisan('survey123:sincronizar-programado')->assertSuccessful();

    Queue::assertNothingPushed();
});

test('respeta el intervalo desde que terminó la última sincronización, sea manual o automática', function () {
    Carbon::setTestNow('2026-11-20 10:00:00');
    $empresa = Company::factory()->create();
    $servicio = app(EstadoSincronizacion::class);
    $servicio->iniciar($empresa->id, 'Ana');
    $servicio->terminar($empresa->id, ['nuevos' => 0, 'actualizados' => 0, 'sin_cambios' => 0, 'fotos' => 0, 'errores' => 0]);

    Carbon::setTestNow('2026-11-20 10:59:00');
    $this->artisan('survey123:sincronizar-programado')->assertSuccessful();
    Queue::assertNothingPushed();

    Carbon::setTestNow('2026-11-20 11:01:00');
    $this->artisan('survey123:sincronizar-programado')->assertSuccessful();
    Queue::assertPushed(SincronizarSurvey123Job::class, 1);
});

test('el intervalo configurable nunca baja de 5 minutos', function () {
    Carbon::setTestNow('2026-11-20 10:00:00');
    $empresa = Company::factory()->create();
    config(['services.survey123.sincronizacion_cada_minutos' => 1]);
    $servicio = app(EstadoSincronizacion::class);
    $servicio->iniciar($empresa->id);
    $servicio->terminar($empresa->id, ['nuevos' => 0, 'actualizados' => 0, 'sin_cambios' => 0, 'fotos' => 0, 'errores' => 0]);

    Carbon::setTestNow('2026-11-20 10:03:00');
    $this->artisan('survey123:sincronizar-programado')->assertSuccessful();
    Queue::assertNothingPushed();
});

test('tras un fallo reintenta cuando pasa el intervalo', function () {
    Carbon::setTestNow('2026-11-20 10:00:00');
    $empresa = Company::factory()->create();
    $servicio = app(EstadoSincronizacion::class);
    $servicio->iniciar($empresa->id);
    $servicio->fallar($empresa->id, 'sin conexión');

    Carbon::setTestNow('2026-11-20 11:05:00');
    $this->artisan('survey123:sincronizar-programado')->assertSuccessful();

    Queue::assertPushed(SincronizarSurvey123Job::class, 1);
});

test('con varias empresas y sin indicar cuál, se salta', function () {
    Company::factory()->count(2)->create();

    $this->artisan('survey123:sincronizar-programado')->expectsOutputToContain('hay varias empresas')->assertSuccessful();

    Queue::assertNothingPushed();
});

test('con varias empresas usa la configurada', function () {
    [$a, $b] = [Company::factory()->create(), Company::factory()->create()];
    config(['services.survey123.empresa_id' => $b->id]);

    $this->artisan('survey123:sincronizar-programado')->assertSuccessful();

    Queue::assertPushed(SincronizarSurvey123Job::class, fn ($job) => $job->companyId === $b->id);
    Queue::assertNotPushed(SincronizarSurvey123Job::class, fn ($job) => $job->companyId === $a->id);
});

test('sin ninguna empresa no hace nada', function () {
    $this->artisan('survey123:sincronizar-programado')->assertSuccessful();

    Queue::assertNothingPushed();
});

test('la sincronización automática no deja la lista del botón bloqueada: el botón respeta su estado', function () {
    $empresa = Company::factory()->create();
    $this->artisan('survey123:sincronizar-programado')->assertSuccessful();

    expect(app(EstadoSincronizacion::class)->enCurso($empresa->id))->toBeTrue()
        ->and(app(EstadoSincronizacion::class)->iniciar($empresa->id))->toBe('Ya hay una sincronización en curso.');
});
