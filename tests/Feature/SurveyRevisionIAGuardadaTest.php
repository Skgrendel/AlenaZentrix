<?php

use App\Enums\EstadoSurveyRegistro;
use App\Livewire\Coordinador\SurveyAuditoria\SurveyRegistroShow;
use App\Models\Company;
use App\Models\RevisionFotosIA;
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

function usuarioDeRevisionIA(string $rol): User
{
    test()->seed(PermissionsSeeder::class);
    $user = User::factory()->create(['company_id' => Company::factory()->create()->id]);
    $user->assignRole($rol);

    return $user;
}

function registroConFotoParaIA(User $user): SurveyRegistro
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

function respuestaIAConResumen(string $resumen): array
{
    $veredicto = [
        'fotos' => [['numero' => 1, 'campo_declarado' => 'foto_fachada', 'muestra' => 'fachada', 'coincide_con_campo' => true, 'calidad' => 'buena', 'observacion' => '']],
        'fotos_repetidas' => false,
        'suspension_visible' => null,
        'datos_leidos' => ['contrato' => null, 'serie_medidor' => null, 'lectura_medidor' => null, 'contrato_coincide' => null, 'serie_coincide' => null, 'lectura_coincide' => null],
        'hallazgos' => [],
        'resumen' => $resumen,
    ];

    return ['choices' => [['message' => ['content' => json_encode($veredicto)]]], 'usage' => ['prompt_tokens' => 1000, 'completion_tokens' => 100]];
}

test('revisar y guardar conserva el veredicto, el modelo y el costo sin tocar la auditoría', function () {
    config(['services.openai.api_key' => 'clave-de-prueba', 'services.openai.modelo' => 'gpt-4o']);
    Http::fake(['api.openai.com/*' => Http::response(respuestaIAConResumen('Todo en orden.'))]);
    $user = usuarioDeRevisionIA('Coordinador');
    $registro = registroConFotoParaIA($user);
    $hallazgosAntes = $registro->hallazgos;

    $revision = app(RevisorFotosIA::class)->revisarYGuardar($registro, $user->id);

    expect($revision->exists)->toBeTrue()
        ->and($revision->modelo)->toBe('gpt-4o')
        ->and($revision->veredicto['resumen'])->toBe('Todo en orden.')
        ->and($revision->fotos)->toBe(1)
        ->and($revision->tokens_entrada)->toBe(1000)
        ->and($revision->costo_usd)->toEqualWithDelta(1000 / 1e6 * 2.5 + 100 / 1e6 * 10, 0.00001)
        ->and($revision->user_id)->toBe($user->id)
        ->and($revision->instrucciones_hash)->toBe(sha1(RevisorFotosIA::INSTRUCCIONES_RECOMENDADAS))
        ->and($registro->fresh()->estado)->toBe(EstadoSurveyRegistro::Pendiente)
        ->and($registro->fresh()->hallazgos)->toBe($hallazgosAntes);
});

test('el detalle ofrece revisar con IA a quien puede revisar y dice que aún no hay revisión', function () {
    $user = usuarioDeRevisionIA('Coordinador');
    $registro = registroConFotoParaIA($user);

    $this->actingAs($user)->get(route('operaciones.survey-auditoria.show', $registro))
        ->assertOk()
        ->assertSee('Revisión de fotos con IA')
        ->assertSee('Todavía no se ha revisado con IA.')
        ->assertSee('Revisar con IA');
});

test('quien no puede revisar ve la sugerencia pero no el botón', function () {
    $user = usuarioDeRevisionIA('Legal');
    $registro = registroConFotoParaIA($user);

    $this->actingAs($user)->get(route('operaciones.survey-auditoria.show', $registro))
        ->assertOk()
        ->assertSee('Todavía no se ha revisado con IA.')
        ->assertDontSee('Revisar con IA');

    Livewire::actingAs($user)->test(SurveyRegistroShow::class, ['registro' => $registro])->call('revisarConIA')->assertForbidden();
    expect(RevisionFotosIA::query()->count())->toBe(0);
});

test('el botón guarda la revisión y el detalle muestra la última', function () {
    config(['services.openai.api_key' => 'clave-de-prueba']);
    $user = usuarioDeRevisionIA('Coordinador');
    $registro = registroConFotoParaIA($user);

    Http::fake(['api.openai.com/*' => Http::sequence()
        ->push(respuestaIAConResumen('Primera revisión.'))
        ->push(respuestaIAConResumen('Segunda revisión.'))]);

    $componente = Livewire::actingAs($user)->test(SurveyRegistroShow::class, ['registro' => $registro]);

    $componente->call('revisarConIA')->assertSee('Primera revisión.')->assertSee('Volver a revisar');
    $componente->call('revisarConIA')->assertSee('Segunda revisión.')->assertDontSee('Primera revisión.');

    expect(RevisionFotosIA::query()->where('survey_registro_id', $registro->id)->count())->toBe(2);
});

test('si la IA falla no se guarda nada y la pantalla sigue funcionando', function () {
    config(['services.openai.api_key' => 'clave-de-prueba']);
    Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'Rate limit']], 429)]);
    $user = usuarioDeRevisionIA('Coordinador');
    $registro = registroConFotoParaIA($user);

    Livewire::actingAs($user)->test(SurveyRegistroShow::class, ['registro' => $registro])
        ->call('revisarConIA')
        ->assertOk()
        ->assertSee('Todavía no se ha revisado con IA.');

    expect(RevisionFotosIA::query()->count())->toBe(0);
});

test('un registro sin fotos no ofrece la revisión con IA', function () {
    $user = usuarioDeRevisionIA('Coordinador');
    $registro = SurveyRegistro::factory()->create(['company_id' => $user->company_id]);

    $this->actingAs($user)->get(route('operaciones.survey-auditoria.show', $registro))
        ->assertOk()
        ->assertDontSee('Revisar con IA');
});
