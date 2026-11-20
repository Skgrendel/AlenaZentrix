<?php

use App\Models\Company;
use App\Models\OrdenJob;
use App\Models\personals;
use App\Models\SurveyRegistro;
use App\Models\User;
use App\Notifications\OrdenAsignadaPush;
use App\Services\AvisoDeOrdenAsignada;
use App\Services\coordinador\AsignarAuditoriaSurveyService;
use App\Services\coordinador\DataGisServices;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

// Estas pruebas no deben llamar al portal real del GIS.
beforeEach(function () {
    $this->mock(DataGisServices::class, function ($mock): void {
        $mock->shouldReceive('consultarContrato')->andReturn(['error' => 'Servicio GIS no disponible']);
    });
});

function avisosConfigurados(bool $si = true): void
{
    config([
        'webpush.vapid.public_key' => $si ? 'clave-publica-de-prueba' : null,
        'webpush.vapid.private_key' => $si ? 'clave-privada-de-prueba' : null,
    ]);
}

/** @return array{0: User, 1: personals} una persona de campo con su usuario */
function personaDeCampo(?Company $empresa = null, string $rol = 'Lector'): array
{
    test()->seed(PermissionsSeeder::class);

    $empresa ??= Company::factory()->create();
    $personal = personals::factory()->create(['company_id' => $empresa->id]);
    $usuario = User::factory()->create(['company_id' => $empresa->id, 'personals_id' => $personal->id]);
    $usuario->assignRole($rol);

    return [$usuario, $personal];
}

function suscripcion(string $endpoint = 'https://fcm.googleapis.com/fcm/send/abc123'): array
{
    return ['endpoint' => $endpoint, 'keys' => ['p256dh' => 'clave-p256dh', 'auth' => 'clave-auth'], 'contentEncoding' => 'aes128gcm'];
}

// ── Suscripción del celular ──────────────────────────────────────────────────

test('la persona activa los avisos y su celular queda registrado', function () {
    [$usuario] = personaDeCampo();

    $this->actingAs($usuario)->postJson(route('tecnico.avisos.guardar'), suscripcion())
        ->assertOk()->assertJson(['activado' => true]);

    expect($usuario->pushSubscriptions()->count())->toBe(1)
        ->and($usuario->pushSubscriptions()->first()->endpoint)->toBe('https://fcm.googleapis.com/fcm/send/abc123');
});

test('activar dos veces el mismo celular no lo duplica, y desactivar lo borra', function () {
    [$usuario] = personaDeCampo();

    $this->actingAs($usuario)->postJson(route('tecnico.avisos.guardar'), suscripcion())->assertOk();
    $this->actingAs($usuario)->postJson(route('tecnico.avisos.guardar'), suscripcion())->assertOk();
    expect($usuario->pushSubscriptions()->count())->toBe(1);

    $this->actingAs($usuario)->deleteJson(route('tecnico.avisos.quitar'), ['endpoint' => suscripcion()['endpoint']])->assertOk();
    expect($usuario->pushSubscriptions()->count())->toBe(0);
});

test('si otra persona inicia sesión en el mismo celular, los avisos pasan a ella y no a la anterior', function () {
    [$primera] = personaDeCampo();
    [$segunda] = personaDeCampo();

    $this->actingAs($primera)->postJson(route('tecnico.avisos.guardar'), suscripcion())->assertOk();
    $this->actingAs($segunda)->postJson(route('tecnico.avisos.guardar'), suscripcion())->assertOk();

    expect($primera->pushSubscriptions()->count())->toBe(0)
        ->and($segunda->pushSubscriptions()->count())->toBe(1);
});

test('una persona no puede borrar la suscripción de otra', function () {
    [$dueña] = personaDeCampo();
    [$otra] = personaDeCampo();
    $this->actingAs($dueña)->postJson(route('tecnico.avisos.guardar'), suscripcion())->assertOk();

    $this->actingAs($otra)->deleteJson(route('tecnico.avisos.quitar'), ['endpoint' => suscripcion()['endpoint']])->assertOk();

    expect($dueña->pushSubscriptions()->count())->toBe(1);
});

test('la suscripción se valida: solo https, con sus claves, y solo quien está en sesión', function () {
    [$usuario] = personaDeCampo();

    $this->actingAs($usuario)->postJson(route('tecnico.avisos.guardar'), suscripcion('http://inseguro.test/x'))->assertJsonValidationErrors('endpoint');
    $this->actingAs($usuario)->postJson(route('tecnico.avisos.guardar'), ['endpoint' => 'https://fcm.googleapis.com/x'])->assertJsonValidationErrors(['keys.p256dh', 'keys.auth']);
    $this->actingAs($usuario)->postJson(route('tecnico.avisos.guardar'), array_merge(suscripcion(), ['contentEncoding' => 'raro']))->assertJsonValidationErrors('contentEncoding');
    expect($usuario->pushSubscriptions()->count())->toBe(0);

    auth()->logout();
    $this->postJson(route('tecnico.avisos.guardar'), suscripcion())->assertUnauthorized();
});

// ── Envío al asignar ─────────────────────────────────────────────────────────

test('al asignar una auditoría de suspensiones el aviso lleva solo la OT del técnico', function () {
    avisosConfigurados();
    Notification::fake();
    [$auditor, $personal] = personaDeCampo(rol: 'Lector');
    $registro = SurveyRegistro::factory()->create(['company_id' => $auditor->company_id, 'orden_trabajo' => '319898201', 'contrato' => '489559']);

    $orden = app(AsignarAuditoriaSurveyService::class)->asignar($registro, $personal->id);

    Notification::assertSentTo($auditor, OrdenAsignadaPush::class, function (OrdenAsignadaPush $aviso) use ($orden) {
        $mensaje = $aviso->toWebPush(new User, $aviso)->toArray();
        $todo = json_encode($mensaje);

        return $aviso->texto === 'OT 319898201'
            && $mensaje['body'] === 'OT 319898201'
            && $mensaje['data']['url'] === route('tecnico.ordenes.show', $orden, absolute: false)
            && ! str_contains($todo, '489559');
    });
});

test('sin las claves de los avisos configuradas no se envía nada y la asignación sigue', function () {
    avisosConfigurados(false);
    Notification::fake();
    [, $personal] = personaDeCampo();
    $registro = SurveyRegistro::factory()->create(['company_id' => $personal->company_id]);

    $orden = app(AsignarAuditoriaSurveyService::class)->asignar($registro, $personal->id);

    Notification::assertNothingSent();
    expect($orden->exists)->toBeTrue();
});

test('si el envío falla, la orden queda asignada igual', function () {
    avisosConfigurados();
    $this->mock(Dispatcher::class, fn ($m) => $m->shouldReceive('send')->andThrow(new RuntimeException('Google no responde')));
    [, $personal] = personaDeCampo();
    $registro = SurveyRegistro::factory()->create(['company_id' => $personal->company_id]);

    $orden = app(AsignarAuditoriaSurveyService::class)->asignar($registro, $personal->id);

    expect($orden->exists)->toBeTrue()->and($orden->estado->value)->toBe('asignada');
});

test('una persona sin usuario en el sistema no rompe la asignación ni recibe aviso', function () {
    avisosConfigurados();
    Notification::fake();
    $empresa = Company::factory()->create();
    $personal = personals::factory()->create(['company_id' => $empresa->id]);
    $registro = SurveyRegistro::factory()->create(['company_id' => $empresa->id]);

    app(AsignarAuditoriaSurveyService::class)->asignar($registro, $personal->id);

    Notification::assertNothingSent();
});

test('una orden normal usa su propio número y varias órdenes a la vez mandan un solo aviso con la cantidad', function () {
    avisosConfigurados();
    Notification::fake();
    [$tecnico, $personal] = personaDeCampo();
    $una = OrdenJob::factory()->create(['company_id' => $tecnico->company_id, 'personals_id' => $personal->id, 'numero_orden' => 'ODT-555']);
    $otra = OrdenJob::factory()->create(['company_id' => $tecnico->company_id, 'personals_id' => $personal->id]);
    $tercera = OrdenJob::factory()->create(['company_id' => $tecnico->company_id, 'personals_id' => $personal->id]);

    AvisoDeOrdenAsignada::enviar($una->load('personal.usuario'));
    Notification::assertSentTo($tecnico, OrdenAsignadaPush::class, fn ($aviso) => $aviso->texto === 'OT ODT-555');

    AvisoDeOrdenAsignada::enviarVarias(collect([$una, $otra, $tercera]), $tecnico);
    Notification::assertSentTo($tecnico, OrdenAsignadaPush::class, fn ($aviso) => $aviso->texto === 'Tienes 3 órdenes nuevas'
        && $aviso->url === route('tecnico.mis-ordenes', absolute: false));

    Notification::assertSentToTimes($tecnico, OrdenAsignadaPush::class, 2);
});

// ── Botón y service worker ───────────────────────────────────────────────────

test('el botón de avisos aparece para quien ve sus órdenes, solo si el servidor los tiene configurados', function () {
    [$tecnico] = personaDeCampo(rol: 'Lector');
    [$legal] = personaDeCampo(rol: 'Legal');

    avisosConfigurados();
    $this->actingAs($tecnico)->get(route('tecnico.mis-ordenes'))->assertOk()
        ->assertSee('data-test="avisos-de-ordenes"', false)->assertSee('Activar avisos de órdenes');
    $this->actingAs($legal)->get(route('dashboard'))->assertOk()->assertDontSee('data-test="avisos-de-ordenes"', false);

    avisosConfigurados(false);
    $this->actingAs($tecnico)->get(route('tecnico.mis-ordenes'))->assertOk()->assertDontSee('data-test="avisos-de-ordenes"', false);
});

test('el service worker muestra el aviso y abre solo rutas del propio sitio', function () {
    $sw = file_get_contents(public_path('sw.js'));

    expect($sw)->toContain("addEventListener('push'")
        ->and($sw)->toContain("addEventListener('notificationclick'")
        ->and($sw)->toContain('destino.origin !== self.location.origin')
        ->and($sw)->toContain("const VERSION = 'v2'");
});

test('el botón de avisos explica el motivo real del fallo en lugar de un mensaje genérico', function () {
    avisosConfigurados();
    [$tecnico] = personaDeCampo(rol: 'Lector');

    $html = $this->actingAs($tecnico)->get(route('tecnico.mis-ordenes'))->assertOk()->getContent();

    foreach ([
        'Tu sesión venció', 'no tiene permiso para activar los avisos', 'no dio permiso para mostrar avisos',
        'servicio de avisos', 'clave de avisos del servidor no es válida', 'conexión segura (HTTPS)', 'No se pudo comunicar con el servidor',
    ] as $frase) {
        expect($html)->toContain($frase);
    }

    expect($html)->toContain("fallo.name = 'Http' + respuesta.status")
        ->and($html)->toContain('this.motivo(error)')
        ->and($html)->not->toContain('No se pudieron activar los avisos. Inténtalo de nuevo.');
});
