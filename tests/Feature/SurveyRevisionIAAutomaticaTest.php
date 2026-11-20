<?php

use App\Jobs\RevisarFotosIAJob;
use App\Livewire\Admin\ConfiguracionRevisionFotosIA;
use App\Livewire\Coordinador\SurveyAuditoria\SurveyRegistrosIndex;
use App\Models\AjusteRevisionIA;
use App\Models\Company;
use App\Models\RevisionFotosIA;
use App\Models\SurveyAdjunto;
use App\Models\SurveyRegistro;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function empresaConRevisionAutomatica(bool $encendida): Company
{
    $empresa = Company::factory()->create();
    AjusteRevisionIA::query()->create([
        'company_id' => $empresa->id, 'modelo' => 'gpt-4o', 'detalle_imagen' => 'high',
        'instrucciones' => 'Instrucciones de prueba para la revisión de las fotos de suspensión.',
        'revision_automatica' => $encendida,
    ]);

    return $empresa;
}

function registroConFotoEnEmpresa(Company $empresa, ?DateTimeInterface $fotoCreada = null): SurveyRegistro
{
    Storage::fake('s3');
    $registro = SurveyRegistro::factory()->create(['company_id' => $empresa->id]);
    Storage::disk('s3')->put("survey123/{$registro->id}/f.jpg", 'bytes');
    $foto = SurveyAdjunto::query()->create([
        'survey_registro_id' => $registro->id, 'attachment_id' => 1, 'campo' => 'foto_fachada',
        'nombre' => 'f.jpg', 'ruta' => "survey123/{$registro->id}/f.jpg", 'hash' => sha1($registro->id),
    ]);

    if ($fotoCreada) {
        $foto->forceFill(['created_at' => $fotoCreada])->save();
    }

    return $registro;
}

function respuestaIASimple(bool $conProblema = false): array
{
    $veredicto = [
        'fotos' => [['numero' => 1, 'campo_declarado' => 'foto_fachada', 'muestra' => $conProblema ? 'medidor' : 'fachada', 'coincide_con_campo' => ! $conProblema, 'calidad' => 'buena', 'observacion' => '']],
        'fotos_repetidas' => false,
        'suspension_visible' => null,
        'datos_leidos' => ['contrato' => null, 'serie_medidor' => null, 'lectura_medidor' => null, 'contrato_coincide' => null, 'serie_coincide' => null, 'lectura_coincide' => null],
        'hallazgos' => [],
        'resumen' => 'Resumen de prueba.',
    ];

    return ['choices' => [['message' => ['content' => json_encode($veredicto)]]], 'usage' => ['prompt_tokens' => 1000, 'completion_tokens' => 100]];
}

// ── Interruptor en Configuración ─────────────────────────────────────────────

test('la revisión automática viene apagada y se enciende y guarda desde Configuración', function () {
    test()->seed(PermissionsSeeder::class);
    $admin = User::factory()->create(['company_id' => Company::factory()->create()->id]);
    $admin->assignRole('Administrador');

    expect(AjusteRevisionIA::de($admin->company_id)->revision_automatica)->toBeFalse();

    Livewire::actingAs($admin)->test(ConfiguracionRevisionFotosIA::class)
        ->assertSet('revision_automatica', false)
        ->set('revision_automatica', true)
        ->call('guardar')
        ->assertHasNoErrors();

    expect(AjusteRevisionIA::de($admin->company_id)->revision_automatica)->toBeTrue();
});

// ── Job ──────────────────────────────────────────────────────────────────────

test('el job guarda la revisión del registro cuando el interruptor está encendido', function () {
    config(['services.openai.api_key' => 'clave-de-prueba']);
    Http::fake(['api.openai.com/*' => Http::response(respuestaIASimple())]);
    $registro = registroConFotoEnEmpresa(empresaConRevisionAutomatica(true), now()->subHour());

    RevisarFotosIAJob::dispatchSync($registro->id);

    expect(RevisionFotosIA::query()->where('survey_registro_id', $registro->id)->count())->toBe(1)
        ->and($registro->fresh()->estado)->toBe($registro->estado);
});

test('el job automático no hace nada si el interruptor se apagó mientras esperaba en la cola', function () {
    config(['services.openai.api_key' => 'clave-de-prueba']);
    Http::fake();
    $registro = registroConFotoEnEmpresa(empresaConRevisionAutomatica(false), now()->subHour());

    RevisarFotosIAJob::dispatchSync($registro->id);

    Http::assertNothingSent();
    expect(RevisionFotosIA::query()->count())->toBe(0);
});

test('el job lanzado a mano por tandas no depende del interruptor', function () {
    config(['services.openai.api_key' => 'clave-de-prueba']);
    Http::fake(['api.openai.com/*' => Http::response(respuestaIASimple())]);
    $registro = registroConFotoEnEmpresa(empresaConRevisionAutomatica(false), now()->subHour());

    RevisarFotosIAJob::dispatchSync($registro->id, automatica: false);

    expect(RevisionFotosIA::query()->count())->toBe(1);
});

test('el job no vuelve a cobrar si ya hay una revisión más nueva que las fotos', function () {
    config(['services.openai.api_key' => 'clave-de-prueba']);
    Http::fake(['api.openai.com/*' => Http::response(respuestaIASimple())]);
    $registro = registroConFotoEnEmpresa(empresaConRevisionAutomatica(true), now()->subHour());

    RevisarFotosIAJob::dispatchSync($registro->id);
    RevisarFotosIAJob::dispatchSync($registro->id);

    Http::assertSentCount(1);
    expect(RevisionFotosIA::query()->count())->toBe(1);
});

test('si llegan fotos nuevas después de la revisión, el job revisa de nuevo y conserva el historial', function () {
    config(['services.openai.api_key' => 'clave-de-prueba']);
    Http::fake(['api.openai.com/*' => Http::response(respuestaIASimple())]);
    $registro = registroConFotoEnEmpresa(empresaConRevisionAutomatica(true), now()->subHours(2));

    RevisarFotosIAJob::dispatchSync($registro->id);
    RevisionFotosIA::query()->update(['created_at' => now()->subHour()]);

    SurveyAdjunto::query()->create([
        'survey_registro_id' => $registro->id, 'attachment_id' => 2, 'campo' => 'foto_medidor',
        'nombre' => 'm.jpg', 'ruta' => "survey123/{$registro->id}/f.jpg", 'hash' => sha1('otra'),
    ]);

    RevisarFotosIAJob::dispatchSync($registro->id);

    expect(RevisionFotosIA::query()->count())->toBe(2);
});

test('sin clave de OpenAI el job no llama al proveedor ni falla', function () {
    config(['services.openai.api_key' => null]);
    Http::fake();
    $registro = registroConFotoEnEmpresa(empresaConRevisionAutomatica(true), now()->subHour());

    RevisarFotosIAJob::dispatchSync($registro->id);

    Http::assertNothingSent();
});

// ── Observaciones y lista ────────────────────────────────────────────────────

test('la revisión marca si tiene observaciones según el veredicto', function () {
    $limpio = json_decode(respuestaIASimple()['choices'][0]['message']['content'], true);
    $conProblema = json_decode(respuestaIASimple(true)['choices'][0]['message']['content'], true);
    $conAlerta = ['hallazgos' => [['severidad' => 'alerta', 'detalle' => 'x']]] + $limpio;
    $soloInfo = ['hallazgos' => [['severidad' => 'info', 'detalle' => 'x']]] + $limpio;
    $noCoincide = ['datos_leidos' => ['serie_coincide' => false, 'contrato_coincide' => true, 'lectura_coincide' => null]] + $limpio;
    $repetidas = ['fotos_repetidas' => true] + $limpio;

    expect(RevisionFotosIA::hayObservaciones($limpio))->toBeFalse()
        ->and(RevisionFotosIA::hayObservaciones($soloInfo))->toBeFalse()
        ->and(RevisionFotosIA::hayObservaciones($conProblema))->toBeTrue()
        ->and(RevisionFotosIA::hayObservaciones($conAlerta))->toBeTrue()
        ->and(RevisionFotosIA::hayObservaciones($noCoincide))->toBeTrue()
        ->and(RevisionFotosIA::hayObservaciones($repetidas))->toBeTrue();
});

test('la lista muestra la columna de IA y filtra por sin revisar, con y sin observaciones', function () {
    test()->seed(PermissionsSeeder::class);
    $user = User::factory()->create(['company_id' => Company::factory()->create()->id]);
    $user->assignRole('Coordinador');

    $nueva = SurveyRegistro::factory()->create(['company_id' => $user->company_id, 'orden_trabajo' => '300000001']);
    $limpia = SurveyRegistro::factory()->create(['company_id' => $user->company_id, 'orden_trabajo' => '300000002']);
    $dudosa = SurveyRegistro::factory()->create(['company_id' => $user->company_id, 'orden_trabajo' => '300000003']);

    foreach ([[$limpia, respuestaIASimple()], [$dudosa, respuestaIASimple(true)]] as [$registro, $respuesta]) {
        $registro->revisionesIA()->create([
            'modelo' => 'gpt-4o', 'detalle_imagen' => 'high', 'instrucciones_hash' => sha1('x'),
            'veredicto' => json_decode($respuesta['choices'][0]['message']['content'], true),
            'fotos' => 1, 'tokens_entrada' => 1, 'tokens_salida' => 1,
        ]);
    }

    $lista = Livewire::actingAs($user)->test(SurveyRegistrosIndex::class)
        ->assertSee('Sin revisar')->assertSee('Con observaciones')->assertSee('Sin observaciones');

    $lista->filterTable('revision_ia', 'sin_revisar')->assertCanSeeTableRecords([$nueva])->assertCanNotSeeTableRecords([$limpia, $dudosa]);
    $lista->filterTable('revision_ia', 'con_observaciones')->assertCanSeeTableRecords([$dudosa])->assertCanNotSeeTableRecords([$nueva, $limpia]);
    $lista->filterTable('revision_ia', 'sin_observaciones')->assertCanSeeTableRecords([$limpia])->assertCanNotSeeTableRecords([$nueva, $dudosa]);
});

// ── Comando por tandas ───────────────────────────────────────────────────────

test('el comando encola solo los pendientes, respeta el límite y puede simular sin encolar', function () {
    Bus::fake();
    config(['services.openai.api_key' => 'clave-de-prueba']);
    $empresa = empresaConRevisionAutomatica(false);

    $pendientes = collect([1, 2, 3])->map(fn () => registroConFotoEnEmpresa($empresa, now()->subHours(3)));
    $revisado = registroConFotoEnEmpresa($empresa, now()->subHours(3));
    $revisado->revisionesIA()->create([
        'modelo' => 'gpt-4o', 'detalle_imagen' => 'high', 'instrucciones_hash' => sha1('x'),
        'veredicto' => json_decode(respuestaIASimple()['choices'][0]['message']['content'], true),
        'fotos' => 1, 'tokens_entrada' => 1, 'tokens_salida' => 1,
    ]);
    SurveyRegistro::factory()->create(['company_id' => $empresa->id]); // sin fotos: no cuenta

    $this->artisan('survey123:revisar-ia', ['--simular' => true])->assertSuccessful();
    Bus::assertNothingDispatched();

    $this->artisan('survey123:revisar-ia', ['--limite' => 2])->assertSuccessful();
    Bus::assertDispatchedTimes(RevisarFotosIAJob::class, 2);
    Bus::assertDispatched(RevisarFotosIAJob::class, fn ($job) => $job->automatica === false);
});

test('el comando avisa si falta la clave de OpenAI', function () {
    config(['services.openai.api_key' => null]);

    $this->artisan('survey123:revisar-ia')->assertFailed();
});
