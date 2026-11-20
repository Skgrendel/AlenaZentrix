<?php

use App\Enums\EstadoSurveyRegistro;
use App\Livewire\Admin\ConfiguracionRevisionFotosIA;
use App\Models\AjusteRevisionIA;
use App\Models\Company;
use App\Models\SurveyAdjunto;
use App\Models\SurveyRegistro;
use App\Models\User;
use App\Services\survey123\RevisorFotosIA;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function usuarioConRol(string $rol): User
{
    test()->seed(PermissionsSeeder::class);
    $user = User::factory()->create(['company_id' => Company::factory()->create()->id]);
    $user->assignRole($rol);

    return $user;
}

function respuestaDeIA(array $extra = []): array
{
    $veredicto = $extra + [
        'fotos' => [['numero' => 1, 'campo_declarado' => 'foto_fachada', 'muestra' => 'medidor', 'coincide_con_campo' => false, 'calidad' => 'regular', 'observacion' => 'Es un primer plano del medidor.']],
        'fotos_repetidas' => false,
        'suspension_visible' => null,
        'datos_leidos' => ['contrato' => '489559', 'serie_medidor' => null, 'lectura_medidor' => '995', 'contrato_coincide' => true, 'serie_coincide' => null, 'lectura_coincide' => true],
        'hallazgos' => [['severidad' => 'error', 'detalle' => 'La fachada es un primer plano del medidor.']],
        'resumen' => 'La foto de fachada no es una fachada.',
    ];

    return ['choices' => [['message' => ['content' => json_encode($veredicto)]]], 'usage' => ['prompt_tokens' => 2000, 'completion_tokens' => 300]];
}

function registroConUnaFoto(User $user): SurveyRegistro
{
    Storage::fake('s3');
    $registro = SurveyRegistro::factory()->create(['company_id' => $user->company_id, 'estado' => EstadoSurveyRegistro::Pendiente]);
    Storage::disk('s3')->put('survey123/x/foto.jpg', 'bytes-de-foto');
    SurveyAdjunto::query()->create([
        'survey_registro_id' => $registro->id, 'attachment_id' => 1, 'campo' => 'foto_fachada',
        'nombre' => 'foto.jpg', 'ruta' => 'survey123/x/foto.jpg', 'hash' => sha1('foto'),
    ]);

    return $registro;
}

test('sin ajustes guardados se usan el modelo y las instrucciones recomendadas', function () {
    $ajuste = AjusteRevisionIA::de(null);

    expect($ajuste->modelo)->toBe(config('services.openai.modelo'))
        ->and($ajuste->instrucciones)->toBe(RevisorFotosIA::INSTRUCCIONES_RECOMENDADAS)
        ->and(AjusteRevisionIA::query()->count())->toBe(0);
});

test('un administrador guarda el modelo, el detalle y las instrucciones de su empresa', function () {
    $admin = usuarioConRol('Administrador');

    Livewire::actingAs($admin)->test(ConfiguracionRevisionFotosIA::class)
        ->set('modelo', 'gpt-4.1')
        ->set('detalle_imagen', 'low')
        ->set('instrucciones', 'Busca que la fachada muestre el frente del predio y no un primer plano del medidor.')
        ->call('guardar')
        ->assertHasNoErrors();

    $guardado = AjusteRevisionIA::de($admin->company_id);

    expect($guardado->exists)->toBeTrue()
        ->and($guardado->modelo)->toBe('gpt-4.1')
        ->and($guardado->detalle_imagen)->toBe('low')
        ->and($guardado->instrucciones)->toContain('primer plano del medidor');
});

test('guardar dos veces actualiza los ajustes en lugar de duplicarlos', function () {
    $admin = usuarioConRol('Administrador');
    $componente = Livewire::actingAs($admin)->test(ConfiguracionRevisionFotosIA::class);

    $componente->set('modelo', 'gpt-4o')->call('guardar')->set('modelo', 'gpt-4.1-mini')->call('guardar');

    expect(AjusteRevisionIA::query()->count())->toBe(1)
        ->and(AjusteRevisionIA::de($admin->company_id)->modelo)->toBe('gpt-4.1-mini');
});

test('valida el modelo, el detalle y que haya instrucciones', function () {
    Livewire::actingAs(usuarioConRol('Administrador'))->test(ConfiguracionRevisionFotosIA::class)
        ->set('modelo', 'gpt 4o; drop')
        ->set('detalle_imagen', 'enorme')
        ->set('instrucciones', 'corto')
        ->call('guardar')
        ->assertHasErrors(['modelo', 'detalle_imagen', 'instrucciones']);

    expect(AjusteRevisionIA::query()->count())->toBe(0);
});

test('restablecer devuelve las instrucciones recomendadas sin guardar', function () {
    $admin = usuarioConRol('Administrador');

    Livewire::actingAs($admin)->test(ConfiguracionRevisionFotosIA::class)
        ->set('instrucciones', 'Un texto cualquiera que escribí yo mismo para la revisión.')
        ->call('restablecerInstrucciones')
        ->assertSet('instrucciones', RevisorFotosIA::INSTRUCCIONES_RECOMENDADAS);

    expect(AjusteRevisionIA::query()->count())->toBe(0);
});

test('quien no tiene el permiso de configuración no entra a la pestaña', function () {
    Livewire::actingAs(usuarioConRol('Coordinador'))->test(ConfiguracionRevisionFotosIA::class)->assertForbidden();
});

test('la pestaña aparece en la página de configuración', function () {
    $this->actingAs(usuarioConRol('Administrador'))
        ->get(route('admin.configuracion'))
        ->assertOk()
        ->assertSee('Revisión de fotos con IA');
});

test('la revisión usa el modelo y las instrucciones configurados y manda los datos del técnico', function () {
    config(['services.openai.api_key' => 'clave-de-prueba']);
    Http::fake(['api.openai.com/*' => Http::response(respuestaDeIA())]);
    $admin = usuarioConRol('Administrador');
    $registro = registroConUnaFoto($admin);

    AjusteRevisionIA::query()->create([
        'company_id' => $admin->company_id, 'modelo' => 'gpt-4.1', 'detalle_imagen' => 'low',
        'instrucciones' => 'Instrucciones propias de la empresa para revisar las fotos de suspensión.',
    ]);

    $r = app(RevisorFotosIA::class)->revisar($registro);

    Http::assertSent(function ($request) use ($registro) {
        $mensajes = $request['messages'];
        $texto = collect($mensajes[1]['content'])->firstWhere('type', 'text')['text'];
        $imagen = collect($mensajes[1]['content'])->firstWhere('type', 'image_url');

        return $request['model'] === 'gpt-4.1'
            && $mensajes[0]['content'] === 'Instrucciones propias de la empresa para revisar las fotos de suspensión.'
            && str_contains($texto, "OT: {$registro->orden_trabajo}")
            && str_contains($texto, "Contrato: {$registro->contrato}")
            && $imagen['image_url']['detail'] === 'low'
            && $request->hasHeader('Authorization', 'Bearer clave-de-prueba');
    });

    expect($r['modelo'])->toBe('gpt-4.1')
        ->and($r['fotos'])->toBe(1)
        ->and($r['costo_usd'])->toEqualWithDelta(2000 / 1e6 * 2.0 + 300 / 1e6 * 8.0, 0.000001);
});

test('un modelo sin tarifa no inventa el costo', function () {
    expect(RevisorFotosIA::costo('modelo-nuevo', 1000, 100))->toBeNull();
});

test('probar muestra el veredicto con lo que hay en pantalla y no toca el registro', function () {
    config(['services.openai.api_key' => 'clave-de-prueba']);
    Http::fake(['api.openai.com/*' => Http::response(respuestaDeIA())]);
    $admin = usuarioConRol('Administrador');
    $registro = registroConUnaFoto($admin);

    Livewire::actingAs($admin)->test(ConfiguracionRevisionFotosIA::class)
        ->set('modelo', 'gpt-4o')
        ->set('instrucciones', 'Instrucciones todavía sin guardar para probar antes de aplicarlas.')
        ->set('registroPrueba', $registro->id)
        ->call('probar')
        ->assertSet('errorPrueba', null)
        ->assertSee('La foto de fachada no es una fachada.')
        ->assertSee('Es un primer plano del medidor.');

    Http::assertSent(fn ($request) => $request['messages'][0]['content'] === 'Instrucciones todavía sin guardar para probar antes de aplicarlas.');

    expect(AjusteRevisionIA::query()->count())->toBe(0)
        ->and($registro->fresh()->estado)->toBe(EstadoSurveyRegistro::Pendiente);
});

test('probar sin elegir registro o sin clave avisa y no llama al proveedor', function () {
    config(['services.openai.api_key' => null]);
    Http::fake();
    $admin = usuarioConRol('Administrador');
    $registro = registroConUnaFoto($admin);

    Livewire::actingAs($admin)->test(ConfiguracionRevisionFotosIA::class)
        ->call('probar')
        ->assertSet('errorPrueba', 'Elige un registro para probar.')
        ->set('registroPrueba', $registro->id)
        ->call('probar')
        ->assertSet('errorPrueba', 'Falta OPENAI_API_KEY en la configuración.');

    Http::assertNothingSent();
});

test('si el proveedor responde con error se muestra el motivo', function () {
    config(['services.openai.api_key' => 'clave-de-prueba']);
    Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'The model does not exist']], 404)]);
    $admin = usuarioConRol('Administrador');
    $registro = registroConUnaFoto($admin);

    Livewire::actingAs($admin)->test(ConfiguracionRevisionFotosIA::class)
        ->set('registroPrueba', $registro->id)
        ->call('probar')
        ->assertSet('resultadoPrueba', null)
        ->assertSee('The model does not exist');
});
