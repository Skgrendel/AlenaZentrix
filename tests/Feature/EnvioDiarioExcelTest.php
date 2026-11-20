<?php

use App\Livewire\Admin\ConfiguracionEnvioDiarioExcel;
use App\Mail\ExcelDiarioDeAuditoria;
use App\Models\AjusteEnvioExcel;
use App\Models\Company;
use App\Models\EnvioExcel;
use App\Models\SurveyRegistro;
use App\Models\User;
use App\Services\survey123\EnvioDiarioDeExcel;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;

uses(RefreshDatabase::class);

// Lunes 23/11/2026, 6:05 p. m. en Colombia.
const LUNES_6_05_PM = '2026-11-23 18:05:00';

afterEach(fn () => Carbon::setTestNow());

function ajusteDeEnvio(?Company $empresa = null, array $datos = []): AjusteEnvioExcel
{
    $empresa ??= Company::factory()->create();

    return AjusteEnvioExcel::query()->create($datos + [
        'company_id' => $empresa->id,
        'activo' => true,
        'hora' => '18:00',
        'dias' => [1, 2, 3, 4, 5],
        'destinatarios' => "jefe@empresa.com\ncontrol@empresa.com",
        'asunto' => 'Auditoría — {fecha} ({registros})',
        'mensaje' => "Buen día.\n\nAdjunto el Excel del día.",
    ]);
}

function registroDelEnvio(Company $empresa, string $ot, string $fecha, array $extra = []): SurveyRegistro
{
    return SurveyRegistro::factory()->create($extra + ['company_id' => $empresa->id, 'orden_trabajo' => $ot, 'fecha_registro' => $fecha]);
}

function administradorDeEnvio(?Company $empresa = null): User
{
    test()->seed(PermissionsSeeder::class);
    $usuario = User::factory()->create(['company_id' => ($empresa ?? Company::factory()->create())->id, 'email' => 'admin@empresa.com']);
    $usuario->assignRole('Administrador');

    return $usuario;
}

// ── Cuándo toca enviar ───────────────────────────────────────────────────────

test('toca enviar cuando está encendido, hoy es uno de los días elegidos y ya pasó la hora', function () {
    $a = ajusteDeEnvio();
    $servicio = app(EnvioDiarioDeExcel::class);

    expect($servicio->pendientes(Carbon::parse('2026-11-23 17:59:00')))->toHaveCount(0)   // antes de la hora
        ->and($servicio->pendientes(Carbon::parse('2026-11-23 18:00:00')))->toHaveCount(1) // a la hora exacta
        ->and($servicio->pendientes(Carbon::parse('2026-11-23 22:30:00')))->toHaveCount(1) // después: se recupera si el servidor estuvo apagado
        ->and($servicio->pendientes(Carbon::parse('2026-11-28 18:05:00')))->toHaveCount(0) // sábado: no está entre los días
        ->and($servicio->pendientes(Carbon::parse('2026-11-23 18:05:00'))->first()->is($a))->toBeTrue();
});

test('apagado no envía nunca, y un sábado sí si se elige el sábado', function () {
    $a = ajusteDeEnvio(datos: ['activo' => false]);
    expect(app(EnvioDiarioDeExcel::class)->pendientes(Carbon::parse(LUNES_6_05_PM)))->toHaveCount(0);

    $a->update(['activo' => true, 'dias' => [6]]);
    expect(app(EnvioDiarioDeExcel::class)->pendientes(Carbon::parse('2026-11-28 18:05:00')))->toHaveCount(1)
        ->and(app(EnvioDiarioDeExcel::class)->pendientes(Carbon::parse(LUNES_6_05_PM)))->toHaveCount(0);
});

test('una vez enviado, no vuelve a tocar ese día, pero sí al siguiente día elegido', function () {
    Mail::fake();
    Carbon::setTestNow(LUNES_6_05_PM);
    $a = ajusteDeEnvio();
    $servicio = app(EnvioDiarioDeExcel::class);

    $servicio->enviar($a);

    expect($servicio->pendientes(now()))->toHaveCount(0)
        ->and($servicio->pendientes(Carbon::parse('2026-11-23 23:30:00')))->toHaveCount(0)
        ->and($servicio->pendientes(Carbon::parse('2026-11-24 18:01:00')))->toHaveCount(1);
});

test('una prueba no cuenta como el envío del día', function () {
    Mail::fake();
    Carbon::setTestNow(LUNES_6_05_PM);
    $a = ajusteDeEnvio();

    app(EnvioDiarioDeExcel::class)->enviar($a, EnvioExcel::PRUEBA, ['admin@empresa.com']);

    expect(app(EnvioDiarioDeExcel::class)->pendientes(now()))->toHaveCount(1);
});

test('si falla, se reintenta cada 15 minutos y se deja de insistir después de 4 intentos en el día', function () {
    Carbon::setTestNow(LUNES_6_05_PM);
    $a = ajusteDeEnvio();
    $servicio = app(EnvioDiarioDeExcel::class);

    foreach (range(1, 4) as $intento) {
        expect($servicio->pendientes(now()))->toHaveCount(1);
        EnvioExcel::query()->create(['company_id' => $a->company_id, 'origen' => 'automatico', 'estado' => 'fallido', 'destinatarios' => ['a@b.com'], 'error' => 'x']);

        expect($servicio->pendientes(now()))->toHaveCount(0); // recién falló: espera
        Carbon::setTestNow(now()->addMinutes(16));
    }

    // Ya van 4 fallos hoy: no insiste más ese día.
    expect($servicio->pendientes(now()))->toHaveCount(0);
});

test('cada empresa tiene su propia hora y no se mezclan', function () {
    $a = ajusteDeEnvio(datos: ['hora' => '18:00']);
    $b = ajusteDeEnvio(datos: ['hora' => '20:00']);

    expect(app(EnvioDiarioDeExcel::class)->pendientes(Carbon::parse(LUNES_6_05_PM))->pluck('id')->all())->toBe([$a->id])
        ->and(app(EnvioDiarioDeExcel::class)->pendientes(Carbon::parse('2026-11-23 20:01:00')))->toHaveCount(2);
});

// ── Qué se envía ─────────────────────────────────────────────────────────────

test('el correo lleva solo los registros de hoy hasta la hora del envío, de esa empresa, con su Excel adjunto', function () {
    Mail::fake();
    Carbon::setTestNow(LUNES_6_05_PM);
    $a = ajusteDeEnvio();
    $otra = Company::factory()->create();

    registroDelEnvio($a->empresa, '300000001', '2026-11-23 00:00:00');                         // hoy, primer segundo: entra
    registroDelEnvio($a->empresa, '300000002', '2026-11-23 17:30:00', ['total_errores' => 2, 'auditado_en' => now()]);
    registroDelEnvio($a->empresa, '300000003', '2026-11-23 18:00:00', ['total_alertas' => 1, 'auditado_en' => now()]);
    registroDelEnvio($a->empresa, '300000004', '2026-11-22 23:59:59');                         // ayer: no entra
    registroDelEnvio($a->empresa, '300000005', '2026-11-23 18:30:00');                         // después de la hora del envío: no entra
    registroDelEnvio($otra, '399999999', '2026-11-23 10:00:00');                               // otra empresa: no entra

    $envio = app(EnvioDiarioDeExcel::class)->enviar($a);

    Mail::assertSent(ExcelDiarioDeAuditoria::class, function (ExcelDiarioDeAuditoria $mail) {
        return $mail->hasTo('jefe@empresa.com') && $mail->hasTo('control@empresa.com')
            && $mail->asunto === 'Auditoría — 23/11/2026 (3)'
            && $mail->registros === 3 && $mail->conErrores === 1 && $mail->conAlertas === 1
            && str_contains($mail->mensaje, 'Adjunto el Excel del día.')
            && $mail->hasSubject('Auditoría — 23/11/2026 (3)')
            && count($mail->attachments()) === 1
            && str_starts_with($mail->nombreDelArchivo, 'auditoria-suspensiones-20261123-1805');
    });

    $mail = Mail::sent(ExcelDiarioDeAuditoria::class)->first();
    $ruta = tempnam(sys_get_temp_dir(), 'ex').'.xlsx';
    file_put_contents($ruta, $mail->contenidoExcel);
    $libro = IOFactory::load($ruta);
    @unlink($ruta);

    $ots = collect($libro->getSheetByName('Auditoría')->toArray(null, false, false, false))->slice(1)->pluck(10)->sort()->values()->all();
    $portada = collect($libro->getSheetByName('Informe')->toArray(null, false, false, false))->flatten()->filter()->values();

    expect($ots)->toBe([300000001, 300000002, 300000003])
        ->and($portada)->toContain('Fecha de registro: hoy 23/11/2026, de 00:00 a 18:05')
        ->and($envio->estado)->toBe('enviado')
        ->and($envio->registros)->toBe(3)
        ->and($envio->destinatarios)->toBe(['jefe@empresa.com', 'control@empresa.com'])
        ->and($envio->origen)->toBe('automatico');
});

test('si hoy no hubo registros se envía igual, avisándolo y sin adjunto', function () {
    Mail::fake();
    Carbon::setTestNow(LUNES_6_05_PM);
    $a = ajusteDeEnvio();
    registroDelEnvio($a->empresa, '300000004', '2026-11-22 10:00:00'); // ayer

    $envio = app(EnvioDiarioDeExcel::class)->enviar($a);

    Mail::assertSent(ExcelDiarioDeAuditoria::class, fn (ExcelDiarioDeAuditoria $m) => $m->registros === 0 && $m->contenidoExcel === null && $m->attachments() === []);
    expect($envio->estado)->toBe('enviado')->and($envio->registros)->toBe(0);

    $html = Mail::sent(ExcelDiarioDeAuditoria::class)->first()->render();
    expect($html)->toContain('no hubo registros')->toContain('no lleva archivo adjunto');
});

test('el correo muestra el mensaje con sus saltos de línea y no deja pasar HTML', function () {
    Mail::fake();
    Carbon::setTestNow(LUNES_6_05_PM);
    $a = ajusteDeEnvio(datos: ['mensaje' => "Línea uno\nLínea <b>dos</b> <script>alert(1)</script>"]);
    registroDelEnvio($a->empresa, '300000001', '2026-11-23 10:00:00');

    app(EnvioDiarioDeExcel::class)->enviar($a);
    $html = Mail::sent(ExcelDiarioDeAuditoria::class)->first()->render();

    expect($html)->toContain('Línea uno<br />')
        ->and($html)->toContain('&lt;b&gt;dos&lt;/b&gt;')
        ->and($html)->not->toContain('<script>');
});

test('si el correo falla, queda la constancia con el motivo y no se lanza ninguna excepción', function () {
    Carbon::setTestNow(LUNES_6_05_PM);
    $a = ajusteDeEnvio();
    Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP caído'));

    $envio = app(EnvioDiarioDeExcel::class)->enviar($a);

    expect($envio->estado)->toBe('fallido')->and($envio->error)->toBe('SMTP caído');
});

test('el comando envía lo pendiente una sola vez al día', function () {
    Mail::fake();
    Carbon::setTestNow(LUNES_6_05_PM);
    $a = ajusteDeEnvio();
    registroDelEnvio($a->empresa, '300000001', '2026-11-23 10:00:00');

    $this->artisan('survey123:enviar-excel-diario')->expectsOutputToContain('Excel enviado a jefe@empresa.com, control@empresa.com (1 registros)')->assertSuccessful();
    $this->artisan('survey123:enviar-excel-diario')->expectsOutputToContain('Ningún envío pendiente')->assertSuccessful();

    Mail::assertSent(ExcelDiarioDeAuditoria::class, 1);

    Carbon::setTestNow('2026-11-28 18:05:00'); // sábado
    $this->artisan('survey123:enviar-excel-diario')->expectsOutputToContain('Ningún envío pendiente');
    Mail::assertSent(ExcelDiarioDeAuditoria::class, 1);
});

test('el comando está programado para correr cada minuto', function () {
    $this->artisan('schedule:list')->expectsOutputToContain('survey123:enviar-excel-diario')->assertSuccessful();
});

test('los correos se leen separados por coma, punto y coma, espacios o líneas, sin repetidos y en minúscula', function () {
    $a = new AjusteEnvioExcel(['destinatarios' => "Uno@Empresa.com, dos@empresa.com;\ntres@empresa.com  uno@empresa.com"]);

    expect($a->correos())->toBe(['uno@empresa.com', 'dos@empresa.com', 'tres@empresa.com']);
});

// ── Pantalla de Configuración ────────────────────────────────────────────────

test('el administrador guarda la configuración y los correos quedan limpios', function () {
    $admin = administradorDeEnvio();

    Livewire::actingAs($admin)->test(ConfiguracionEnvioDiarioExcel::class)
        ->set('activo', true)->set('hora', '07:30')->set('dias', ['1', '3', '5'])
        ->set('destinatarios', 'Jefe@Empresa.com, control@empresa.com; jefe@empresa.com')
        ->set('asunto', 'Resumen {fecha}')->set('mensaje', 'Hola equipo')
        ->call('guardar')->assertHasNoErrors();

    $guardado = AjusteEnvioExcel::de($admin->company_id);

    expect($guardado->exists)->toBeTrue()
        ->and($guardado->activo)->toBeTrue()
        ->and($guardado->hora)->toBe('07:30')
        ->and($guardado->dias)->toBe([1, 3, 5])
        ->and($guardado->correos())->toBe(['jefe@empresa.com', 'control@empresa.com'])
        ->and($guardado->asunto)->toBe('Resumen {fecha}');
});

test('la configuración se valida: hora, días, correos, asunto y mensaje', function () {
    $admin = administradorDeEnvio();

    Livewire::actingAs($admin)->test(ConfiguracionEnvioDiarioExcel::class)
        ->set('activo', true)->set('hora', '25:99')->set('dias', [])->set('destinatarios', '')->set('asunto', '')->set('mensaje', '')
        ->call('guardar')->assertHasErrors(['hora', 'dias', 'destinatarios', 'asunto', 'mensaje']);

    Livewire::actingAs($admin)->test(ConfiguracionEnvioDiarioExcel::class)
        ->set('activo', true)->set('dias', ['1'])->set('destinatarios', 'bueno@empresa.com, esto-no-es-un-correo')
        ->call('guardar')->assertHasErrors(['destinatarios']);

    Livewire::actingAs($admin)->test(ConfiguracionEnvioDiarioExcel::class)
        ->set('activo', true)->set('dias', ['1'])->set('destinatarios', implode(',', array_map(fn ($i) => "p{$i}@empresa.com", range(1, 11))))
        ->call('guardar')->assertHasErrors(['destinatarios']);

    Livewire::actingAs($admin)->test(ConfiguracionEnvioDiarioExcel::class)
        ->set('activo', true)->set('dias', ['9'])->set('destinatarios', 'a@empresa.com')
        ->call('guardar')->assertHasErrors(['dias.0']);

    expect(AjusteEnvioExcel::query()->count())->toBe(0);
});

test('apagado se puede guardar con los demás campos vacíos', function () {
    $admin = administradorDeEnvio();

    Livewire::actingAs($admin)->test(ConfiguracionEnvioDiarioExcel::class)
        ->set('activo', false)->set('dias', [])->set('destinatarios', '')
        ->call('guardar')->assertHasNoErrors();

    expect(AjusteEnvioExcel::de($admin->company_id)->activo)->toBeFalse();
});

test('la pantalla carga lo guardado de la empresa del usuario y no lo de otras', function () {
    $admin = administradorDeEnvio();
    ajusteDeEnvio($admin->company, ['destinatarios' => 'mia@empresa.com', 'hora' => '09:15']);
    ajusteDeEnvio(null, ['destinatarios' => 'ajena@otra.com', 'hora' => '23:00']);

    Livewire::actingAs($admin)->test(ConfiguracionEnvioDiarioExcel::class)
        ->assertSet('hora', '09:15')->assertSet('destinatarios', 'mia@empresa.com')->assertDontSee('ajena@otra.com');
});

test('guardar cambia solo los ajustes de la propia empresa', function () {
    $admin = administradorDeEnvio();
    $ajena = ajusteDeEnvio(null, ['destinatarios' => 'ajena@otra.com', 'hora' => '23:00']);

    Livewire::actingAs($admin)->test(ConfiguracionEnvioDiarioExcel::class)
        ->set('activo', true)->set('hora', '06:00')->set('dias', ['2'])->set('destinatarios', 'mia@empresa.com')
        ->call('guardar')->assertHasNoErrors();

    expect($ajena->fresh()->hora)->toBe('23:00')->and($ajena->fresh()->correos())->toBe(['ajena@otra.com'])
        ->and(AjusteEnvioExcel::query()->count())->toBe(2);
});

test('quien no tiene el permiso de configuración no entra', function () {
    test()->seed(PermissionsSeeder::class);
    $coordinador = User::factory()->create();
    $coordinador->assignRole('Coordinador');

    Livewire::actingAs($coordinador)->test(ConfiguracionEnvioDiarioExcel::class)->assertForbidden();
});

test('«Enviarme una prueba» manda solo a quien la pide, con lo que hay en pantalla, y deja constancia', function () {
    Mail::fake();
    Carbon::setTestNow(LUNES_6_05_PM);
    $admin = administradorDeEnvio();
    registroDelEnvio($admin->company, '300000001', '2026-11-23 10:00:00');

    Livewire::actingAs($admin)->test(ConfiguracionEnvioDiarioExcel::class)
        ->set('destinatarios', 'otros@empresa.com')->set('asunto', 'Prueba {registros}')->set('mensaje', 'Mensaje de prueba')
        ->call('enviarPrueba')->assertHasNoErrors();

    Mail::assertSent(ExcelDiarioDeAuditoria::class, fn ($m) => $m->hasTo('admin@empresa.com') && ! $m->hasTo('otros@empresa.com') && $m->asunto === 'Prueba 1');
    expect(EnvioExcel::query()->where('origen', 'prueba')->count())->toBe(1)
        ->and(AjusteEnvioExcel::query()->count())->toBe(0); // la prueba no guarda nada
});

test('la pantalla muestra los últimos envíos, también los fallidos con su motivo', function () {
    $admin = administradorDeEnvio();
    EnvioExcel::query()->create(['company_id' => $admin->company_id, 'origen' => 'automatico', 'estado' => 'enviado', 'destinatarios' => ['jefe@empresa.com'], 'registros' => 12]);
    EnvioExcel::query()->create(['company_id' => $admin->company_id, 'origen' => 'automatico', 'estado' => 'fallido', 'destinatarios' => ['jefe@empresa.com'], 'registros' => 0, 'error' => 'SMTP caído']);
    EnvioExcel::query()->create(['company_id' => Company::factory()->create()->id, 'origen' => 'automatico', 'estado' => 'enviado', 'destinatarios' => ['ajeno@otra.com'], 'registros' => 99]);

    Livewire::actingAs($admin)->test(ConfiguracionEnvioDiarioExcel::class)
        ->assertSee('12 registro(s)')->assertSee('SMTP caído')->assertSee('Falló')->assertDontSee('ajeno@otra.com');
});

test('la pestaña aparece en Configuración y el nombre del componente coincide con su archivo en Linux', function () {
    $clase = Str::studly('configuracion-envio-diario-excel');

    expect(scandir(app_path('Livewire/Admin')))->toContain($clase.'.php')
        ->and((new ReflectionClass(ConfiguracionEnvioDiarioExcel::class))->getShortName())->toBe($clase);

    $this->actingAs(administradorDeEnvio())->get(route('admin.configuracion'))
        ->assertOk()->assertSee('Envío diario del Excel')->assertSeeLivewire('admin.configuracion-envio-diario-excel');
});
