<?php

use App\Enums\TipoServicio;
use App\Models\Company;
use App\Models\OrdenJob;
use App\Models\personals;
use App\Models\SurveyAdjunto;
use App\Models\SurveyRegistro;
use App\Models\User;
use App\Services\survey123\RevisorFotosIA;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function pngDeMapa(): string
{
    $imagen = imagecreatetruecolor(640, 400);
    imagefill($imagen, 0, 0, imagecolorallocate($imagen, 240, 235, 200));
    ob_start();
    imagepng($imagen);

    return (string) ob_get_clean();
}

/**
 * Guarda una revisión de IA del registro. $fotos: lista de [adjunto, qué muestra realmente, campo declarado].
 *
 * @param  array<int, array{0: SurveyAdjunto, 1: string, 2: string}>  $fotos
 */
function revisionDeIA(SurveyRegistro $registro, array $fotos, bool $conMapaDeFotos = true): void
{
    $veredicto = ['fotos' => [], 'fotos_repetidas' => false, 'suspension_visible' => null, 'datos_leidos' => [], 'hallazgos' => [], 'resumen' => 'x'];
    $mapa = [];

    foreach ($fotos as $i => [$adjunto, $muestra, $campo]) {
        $numero = $i + 1;
        $mapa[$numero] = $adjunto->id;
        $veredicto['fotos'][] = ['numero' => $numero, 'campo_declarado' => $campo, 'muestra' => $muestra, 'coincide_con_campo' => $muestra === 'fachada' && $campo === 'foto_fachada', 'calidad' => 'buena', 'observacion' => ''];
    }

    if ($conMapaDeFotos) {
        $veredicto['mapa_de_fotos'] = $mapa;
    }

    $registro->revisionesIA()->create([
        'modelo' => 'gpt-4o', 'detalle_imagen' => 'high', 'instrucciones_hash' => sha1('x'), 'veredicto' => $veredicto,
        'fotos' => count($fotos), 'tokens_entrada' => 1, 'tokens_salida' => 1,
    ]);
}

/** @return array{0: User, 1: OrdenJob, 2: SurveyRegistro} un auditor de campo con su orden asignada */
function auditorConOrden(array $registro = [], bool $conFachada = true, bool $verificada = true): array
{
    test()->seed(PermissionsSeeder::class);
    Storage::fake('s3');

    $empresa = Company::factory()->create();
    $personal = personals::factory()->create(['company_id' => $empresa->id]);
    $auditor = User::factory()->create(['company_id' => $empresa->id, 'personals_id' => $personal->id]);
    $auditor->assignRole('Lector');

    $survey = SurveyRegistro::factory()->create($registro + ['company_id' => $empresa->id]);

    if ($conFachada) {
        Storage::disk('s3')->put("survey123/{$survey->id}/fachada.jpg", 'bytes-de-la-fachada');
        SurveyAdjunto::query()->create([
            'survey_registro_id' => $survey->id, 'attachment_id' => 5, 'campo' => 'foto_fachada', 'nombre' => 'fachada.jpg',
            'content_type' => 'image/jpeg', 'ruta' => "survey123/{$survey->id}/fachada.jpg", 'hash' => sha1('f'),
        ]);
        Storage::disk('s3')->put("survey123/{$survey->id}/medidor.jpg", 'bytes-del-medidor');
        SurveyAdjunto::query()->create([
            'survey_registro_id' => $survey->id, 'attachment_id' => 6, 'campo' => 'foto_medidor', 'nombre' => 'medidor.jpg',
            'content_type' => 'image/jpeg', 'ruta' => "survey123/{$survey->id}/medidor.jpg", 'hash' => sha1('m'),
        ]);

        if ($verificada) {
            revisionDeIA($survey, [[$survey->adjuntos()->where('campo', 'foto_fachada')->first(), 'fachada', 'foto_fachada']]);
        }
    }

    $orden = OrdenJob::factory()->create([
        'company_id' => $empresa->id, 'personals_id' => $personal->id,
        'tipo_servicio' => TipoServicio::AuditoriaCampo, 'tipo_auditoria_campo' => 'visita_calidad_suspensiones',
        'survey_registro_id' => $survey->id,
    ]);

    return [$auditor, $orden, $survey];
}

test('la orden asignada muestra la fachada y el mapa para ubicar el predio', function () {
    [$auditor, $orden] = auditorConOrden();

    $this->actingAs($auditor)->get(route('tecnico.ordenes.show', $orden))
        ->assertOk()
        ->assertSee('Ubicación del predio')
        ->assertSee(route('tecnico.ordenes.referencia.fachada', $orden))
        ->assertSee(route('tecnico.ordenes.referencia.mapa', $orden));
});

test('el auditor recibe la foto de fachada, y solo la de fachada', function () {
    [$auditor, $orden] = auditorConOrden();

    $respuesta = $this->actingAs($auditor)->get(route('tecnico.ordenes.referencia.fachada', $orden));

    $respuesta->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    expect($respuesta->streamedContent())->toBe('bytes-de-la-fachada');
});

test('el mapa se pide a ArcGIS centrado en el punto, se guarda y se reutiliza', function () {
    Http::fake(['services.arcgisonline.com/*' => Http::response(pngDeMapa(), 200, ['Content-Type' => 'image/png'])]);
    [$auditor, $orden, $survey] = auditorConOrden();

    $this->actingAs($auditor)->get(route('tecnico.ordenes.referencia.mapa', $orden))
        ->assertOk()->assertHeader('Content-Type', 'image/png');
    $this->actingAs($auditor)->get(route('tecnico.ordenes.referencia.mapa', $orden))->assertOk();

    Http::assertSentCount(1);
    Http::assertSent(function ($request) {
        [$minX, $minY, $maxX, $maxY] = array_map('floatval', explode(',', $request['bbox']));
        // Punto de la visita (10.407333, -75.488048) en Web Mercator: debe quedar en el centro del cuadro.
        $x = 6378137.0 * deg2rad(-75.488048);
        $y = 6378137.0 * log(tan(M_PI / 4 + deg2rad(10.407333) / 2));

        return abs(($minX + $maxX) / 2 - $x) < 1 && abs(($minY + $maxY) / 2 - $y) < 1
            && $request['size'] === '640,400' && $request['f'] === 'image';
    });
    Storage::disk('s3')->assertExists("survey123/mapas/{$survey->company_id}/{$survey->id}.png");
});

test('si el servicio de mapas falla, el mapa responde 404 y no se guarda nada', function () {
    Http::fake(['services.arcgisonline.com/*' => Http::response('error', 500)]);
    [$auditor, $orden, $survey] = auditorConOrden();

    $this->actingAs($auditor)->get(route('tecnico.ordenes.referencia.mapa', $orden))->assertNotFound();

    Storage::disk('s3')->assertMissing("survey123/mapas/{$survey->company_id}/{$survey->id}.png");
});

test('sin foto de fachada o sin coordenadas no se ofrece lo que falta', function () {
    [$sinFachada, $ordenA] = auditorConOrden(conFachada: false);
    $this->actingAs($sinFachada)->get(route('tecnico.ordenes.show', $ordenA))
        ->assertOk()->assertDontSee(route('tecnico.ordenes.referencia.fachada', $ordenA))->assertSee(route('tecnico.ordenes.referencia.mapa', $ordenA));
    $this->actingAs($sinFachada)->get(route('tecnico.ordenes.referencia.fachada', $ordenA))->assertNotFound();

    [$sinPunto, $ordenB] = auditorConOrden(['latitud' => null, 'longitud' => null]);
    Http::fake();
    $this->actingAs($sinPunto)->get(route('tecnico.ordenes.show', $ordenB))
        ->assertOk()->assertSee(route('tecnico.ordenes.referencia.fachada', $ordenB))->assertDontSee(route('tecnico.ordenes.referencia.mapa', $ordenB));
    $this->actingAs($sinPunto)->get(route('tecnico.ordenes.referencia.mapa', $ordenB))->assertNotFound();
    Http::assertNothingSent();
});

test('una orden que no viene de un registro de suspensión no muestra la tarjeta ni entrega imágenes', function () {
    [$auditor] = auditorConOrden();
    $comun = OrdenJob::factory()->create(['company_id' => $auditor->company_id, 'personals_id' => $auditor->personals_id]);

    $this->actingAs($auditor)->get(route('tecnico.ordenes.show', $comun))->assertOk()->assertDontSee('Ubicación del predio');
    $this->actingAs($auditor)->get(route('tecnico.ordenes.referencia.fachada', $comun))->assertNotFound();
    $this->actingAs($auditor)->get(route('tecnico.ordenes.referencia.mapa', $comun))->assertNotFound();
});

test('otro auditor no puede ver la fachada ni el mapa de una orden que no es suya', function () {
    [, $orden] = auditorConOrden();
    $otraPersona = personals::factory()->create(['company_id' => $orden->company_id]);
    $otro = User::factory()->create(['company_id' => $orden->company_id, 'personals_id' => $otraPersona->id]);
    $otro->assignRole('Lector');

    $this->actingAs($otro)->get(route('tecnico.ordenes.referencia.fachada', $orden))->assertForbidden();
    $this->actingAs($otro)->get(route('tecnico.ordenes.referencia.mapa', $orden))->assertForbidden();
});

test('quien puede ver todas las órdenes y los usuarios de otra empresa', function () {
    [, $orden] = auditorConOrden();
    $coordinador = User::factory()->create(['company_id' => $orden->company_id]);
    $coordinador->assignRole('Administrador');
    $this->actingAs($coordinador)->get(route('tecnico.ordenes.referencia.fachada', $orden))->assertOk();

    $ajeno = User::factory()->create(['company_id' => Company::factory()->create()->id]);
    $ajeno->assignRole('Administrador');
    $this->actingAs($ajeno)->get(route('tecnico.ordenes.referencia.fachada', $orden))->assertNotFound();
});

test('sin iniciar sesión no se entrega nada', function () {
    [, $orden] = auditorConOrden();

    $this->get(route('tecnico.ordenes.referencia.fachada', $orden))->assertRedirect();
    $this->get(route('tecnico.ordenes.referencia.mapa', $orden))->assertRedirect();
});

test('sin revisión de la IA no se muestra la foto de fachada, solo el mapa y un aviso', function () {
    [$auditor, $orden] = auditorConOrden(verificada: false);

    $this->actingAs($auditor)->get(route('tecnico.ordenes.show', $orden))
        ->assertOk()
        ->assertSee('La foto de fachada aún no está verificada.')
        ->assertDontSee(route('tecnico.ordenes.referencia.fachada', $orden))
        ->assertSee(route('tecnico.ordenes.referencia.mapa', $orden));

    $this->actingAs($auditor)->get(route('tecnico.ordenes.referencia.fachada', $orden))->assertNotFound();
});

test('si la IA dice que la fachada es en realidad el medidor, no se le entrega al auditor', function () {
    [$auditor, $orden, $survey] = auditorConOrden(verificada: false);
    revisionDeIA($survey, [[$survey->adjuntos()->where('campo', 'foto_fachada')->first(), 'medidor', 'foto_fachada']]);

    $this->actingAs($auditor)->get(route('tecnico.ordenes.referencia.fachada', $orden))->assertNotFound();
    $this->actingAs($auditor)->get(route('tecnico.ordenes.show', $orden))->assertSee('La foto de fachada aún no está verificada.');
});

test('con varias fotos de fachada se entrega solo la que la IA confirmó', function () {
    [$auditor, $orden, $survey] = auditorConOrden(verificada: false);
    $primera = $survey->adjuntos()->where('campo', 'foto_fachada')->first();

    Storage::disk('s3')->put("survey123/{$survey->id}/fachada2.jpg", 'bytes-de-la-segunda');
    $segunda = SurveyAdjunto::query()->create([
        'survey_registro_id' => $survey->id, 'attachment_id' => 9, 'campo' => 'foto_fachada', 'nombre' => 'fachada2.jpg',
        'content_type' => 'image/jpeg', 'ruta' => "survey123/{$survey->id}/fachada2.jpg", 'hash' => sha1('f2'),
    ]);

    // La primera es un primer plano del medidor puesto como fachada; la segunda sí es una fachada.
    revisionDeIA($survey, [[$primera, 'medidor', 'foto_fachada'], [$segunda, 'fachada', 'foto_fachada']]);

    $respuesta = $this->actingAs($auditor)->get(route('tecnico.ordenes.referencia.fachada', $orden))->assertOk();

    expect($respuesta->streamedContent())->toBe('bytes-de-la-segunda');
});

test('una revisión anterior, sin el vínculo con las fotos, no habilita la fachada', function () {
    [$auditor, $orden, $survey] = auditorConOrden(verificada: false);
    revisionDeIA($survey, [[$survey->adjuntos()->where('campo', 'foto_fachada')->first(), 'fachada', 'foto_fachada']], conMapaDeFotos: false);

    $this->actingAs($auditor)->get(route('tecnico.ordenes.referencia.fachada', $orden))->assertNotFound();
});

test('una fachada confirmada que una revisión nueva desconfirma deja de mostrarse', function () {
    [$auditor, $orden, $survey] = auditorConOrden();
    $this->actingAs($auditor)->get(route('tecnico.ordenes.referencia.fachada', $orden))->assertOk();

    $foto = $survey->adjuntos()->where('campo', 'foto_fachada')->first();
    $survey->revisionesIA()->update(['created_at' => now()->subHour()]);
    revisionDeIA($survey, [[$foto, 'medidor', 'foto_fachada']]);

    $this->actingAs($auditor)->get(route('tecnico.ordenes.referencia.fachada', $orden))->assertNotFound();
});

test('la revisión con IA guarda a qué foto corresponde cada número del veredicto', function () {
    config(['services.openai.api_key' => 'clave-de-prueba']);
    [, , $survey] = auditorConOrden(verificada: false);
    $fotos = $survey->adjuntos()->orderBy('campo')->orderBy('nombre')->get();

    $veredicto = ['fotos' => [], 'fotos_repetidas' => false, 'suspension_visible' => null, 'datos_leidos' => [], 'hallazgos' => [], 'resumen' => 'x'];
    Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => json_encode($veredicto)]]], 'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1]])]);

    $revision = app(RevisorFotosIA::class)->revisarYGuardar($survey);

    expect($revision->veredicto['mapa_de_fotos'])->toBe([1 => $fotos[0]->id, 2 => $fotos[1]->id]);
});
