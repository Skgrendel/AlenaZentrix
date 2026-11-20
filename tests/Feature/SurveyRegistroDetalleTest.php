<?php

use App\Livewire\Coordinador\SurveyAuditoria\SurveyRegistrosIndex;
use App\Models\Company;
use App\Models\OrdenJob;
use App\Models\SurveyAdjunto;
use App\Models\SurveyRegistro;
use App\Models\User;
use App\Services\survey123\AuditoriaSurveyRegistro;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/** @return array{0: User, 1: SurveyRegistro} */
function coordinadorConDetalle(array $registro = []): array
{
    test()->seed(PermissionsSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('Coordinador');

    $survey = SurveyRegistro::factory()->create($registro + [
        'company_id' => $user->company_id,
        'orden_trabajo' => '319898201',
        'contrato' => '489559',
        'justificacion' => 'Se realiza suspensión desde acometida',
    ]);

    return [$user, $survey];
}

function fotoDelRegistro(SurveyRegistro $registro, string $campo, string $nombre, ?string $hash = null, string $contenido = 'bytes-de-foto'): SurveyAdjunto
{
    $ruta = "survey123/{$registro->id}/{$nombre}";
    Storage::disk('s3')->put($ruta, $contenido);

    return SurveyAdjunto::query()->create([
        'survey_registro_id' => $registro->id,
        'attachment_id' => random_int(1, 10_000_000),
        'campo' => $campo,
        'nombre' => $nombre,
        'content_type' => 'image/jpeg',
        'ruta' => $ruta,
        'hash' => $hash ?? sha1($contenido.$nombre),
    ]);
}

test('el detalle muestra los datos reportados, los hallazgos y las fotos', function () {
    Storage::fake('s3');
    [$user, $registro] = coordinadorConDetalle();
    fotoDelRegistro($registro, 'foto_fachada', 'fachada-1.jpg');
    fotoDelRegistro($registro, 'foto_medidor', 'medidor-1.jpg');
    app(AuditoriaSurveyRegistro::class)->auditarYGuardar($registro);

    $this->actingAs($user)
        ->get(route('operaciones.survey-auditoria.show', $registro))
        ->assertOk()
        ->assertSee('Registro OT 319898201')
        ->assertSee('Luis Quiroga')
        ->assertSee('Taponamiento de acometida')
        ->assertSee('Se realiza suspensión desde acometida')
        ->assertSee('Hay 1 foto(s) de fachada y se requieren al menos 3.')
        ->assertSee('fachada-1.jpg')
        ->assertSee('Fotos del técnico (2)');
});

test('la lista de verificación marca las fotos completas y las que faltan', function () {
    Storage::fake('s3');
    [$user, $registro] = coordinadorConDetalle();

    foreach (['a', 'b', 'c'] as $letra) {
        fotoDelRegistro($registro, 'foto_fachada', "fachada-{$letra}.jpg");
    }
    fotoDelRegistro($registro, 'foto_medidor', 'medidor.jpg');

    $this->actingAs($user)
        ->get(route('operaciones.survey-auditoria.show', $registro))
        ->assertOk()
        ->assertSee('✔ 3 de 3')
        ->assertSee('✔ 1 de 1')
        ->assertSee('✖ 0 de 1');
});

test('las fotos de actividad y de medidor no aplican si la suspensión no se ejecutó', function () {
    Storage::fake('s3');
    [$user, $registro] = coordinadorConDetalle(['suspension_ejecutada' => false]);

    $this->actingAs($user)
        ->get(route('operaciones.survey-auditoria.show', $registro))
        ->assertOk()
        ->assertSee('No aplica');
});

test('marca las fotos repetidas dentro del registro y las usadas en otra visita', function () {
    Storage::fake('s3');
    [$user, $registro] = coordinadorConDetalle();
    $otro = SurveyRegistro::factory()->create(['company_id' => $user->company_id]);

    fotoDelRegistro($registro, 'foto_fachada', 'a.jpg', 'hash-repetido');
    fotoDelRegistro($registro, 'foto_fachada', 'b.jpg', 'hash-repetido');
    fotoDelRegistro($registro, 'foto_medidor', 'm.jpg', 'hash-de-otra-visita');
    fotoDelRegistro($otro, 'foto_medidor', 'm-otro.jpg', 'hash-de-otra-visita');

    $this->actingAs($user)
        ->get(route('operaciones.survey-auditoria.show', $registro))
        ->assertOk()
        ->assertSee('Repetida en este registro')
        ->assertSee('Usada en otra visita');
});

test('un registro sin fotos lo indica', function () {
    [$user, $registro] = coordinadorConDetalle();

    $this->actingAs($user)
        ->get(route('operaciones.survey-auditoria.show', $registro))
        ->assertOk()
        ->assertSee('no tiene fotos copiadas');
});

test('el detalle de un registro de otra empresa no existe', function () {
    [$user] = coordinadorConDetalle();
    $ajeno = SurveyRegistro::factory()->create(['company_id' => Company::factory()->create()->id]);

    $this->actingAs($user)
        ->get(route('operaciones.survey-auditoria.show', $ajeno))
        ->assertNotFound();
});

test('sin permiso de órdenes no se puede ver el detalle', function () {
    $this->seed(PermissionsSeeder::class);
    $user = User::factory()->create();
    $registro = SurveyRegistro::factory()->create(['company_id' => $user->company_id]);

    $this->actingAs($user)->get(route('operaciones.survey-auditoria.show', $registro))->assertForbidden();
});

test('la foto se entrega como imagen desde el almacenamiento', function () {
    Storage::fake('s3');
    [$user, $registro] = coordinadorConDetalle();
    $foto = fotoDelRegistro($registro, 'foto_fachada', 'fachada.jpg', contenido: 'contenido-binario');

    $respuesta = $this->actingAs($user)->get(route('operaciones.survey-auditoria.adjuntos.show', $foto));

    $respuesta->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($respuesta->streamedContent())->toBe('contenido-binario');
});

test('un adjunto que no es imagen se entrega como descarga genérica', function () {
    Storage::fake('s3');
    [$user, $registro] = coordinadorConDetalle();
    $foto = fotoDelRegistro($registro, 'foto_fachada', 'raro.html');
    $foto->update(['content_type' => 'text/html']);

    $this->actingAs($user)
        ->get(route('operaciones.survey-auditoria.adjuntos.show', $foto))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/octet-stream');
});

test('si el archivo no está en el almacenamiento responde 404', function () {
    Storage::fake('s3');
    [$user, $registro] = coordinadorConDetalle();
    $foto = fotoDelRegistro($registro, 'foto_fachada', 'fachada.jpg');
    Storage::disk('s3')->delete($foto->ruta);

    $this->actingAs($user)->get(route('operaciones.survey-auditoria.adjuntos.show', $foto))->assertNotFound();
});

test('no se puede ver la foto de un registro de otra empresa', function () {
    Storage::fake('s3');
    [$user] = coordinadorConDetalle();
    $ajeno = SurveyRegistro::factory()->create(['company_id' => Company::factory()->create()->id]);
    $foto = fotoDelRegistro($ajeno, 'foto_fachada', 'ajena.jpg');

    $this->actingAs($user)->get(route('operaciones.survey-auditoria.adjuntos.show', $foto))->assertNotFound();
});

test('un visitante sin sesión no puede ver las fotos', function () {
    Storage::fake('s3');
    $registro = SurveyRegistro::factory()->create();
    $foto = fotoDelRegistro($registro, 'foto_fachada', 'fachada.jpg');

    $this->get(route('operaciones.survey-auditoria.adjuntos.show', $foto))->assertRedirect();
});

test('las fotos se ven en un visor emergente y no abren otra pestaña', function () {
    Storage::fake('s3');
    [$user, $registro] = coordinadorConDetalle();
    fotoDelRegistro($registro, 'foto_fachada', 'fachada-1.jpg');
    fotoDelRegistro($registro, 'foto_medidor', 'medidor-1.jpg');

    $this->actingAs($user)
        ->get(route('operaciones.survey-auditoria.show', $registro))
        ->assertOk()
        ->assertSee('aria-modal="true"', false)
        ->assertSee('Ampliar la foto fachada-1.jpg')
        ->assertSee('Ampliar la foto medidor-1.jpg')
        ->assertDontSee('target="_blank" rel="noopener">'."\n", false);
});

test('el detalle ofrece volver a la lista y no muestra botón para ver la orden', function () {
    [$user, $registro] = coordinadorConDetalle();
    OrdenJob::factory()->create(['company_id' => $user->company_id, 'survey_registro_id' => $registro->id]);

    $this->actingAs($user)
        ->get(route('operaciones.survey-auditoria.show', $registro))
        ->assertOk()
        ->assertSee('Volver a la lista')
        ->assertDontSee('Ver orden');
});

test('los totales y el estado del registro se muestran en la tarjeta de hallazgos', function () {
    [$user, $registro] = coordinadorConDetalle(['total_errores' => 1, 'total_alertas' => 2]);

    $this->actingAs($user)
        ->get(route('operaciones.survey-auditoria.show', $registro))
        ->assertOk()
        ->assertSeeInOrder(['Hallazgos de la auditoría', '1 error', '2 alertas', 'Pendiente por asignar']);
});

test('las fechas de la lista y del detalle se muestran en hora de Colombia, como en el formulario de los técnicos', function () {
    [$user, $survey] = coordinadorConDetalle([
        // La app guarda y lee en hora de Colombia: 16:23 es lo que muestra el formulario de ArcGIS.
        'fecha_registro' => '2026-11-20 16:23:00',
        'creado_en_origen' => '2026-11-20 16:30:02',
        'editado_en_origen' => '2026-11-20 17:05:00',
    ]);

    $this->actingAs($user)->get(route('operaciones.survey-auditoria.show', $survey))
        ->assertOk()
        ->assertSee('20/11/2026 04:23 pm')
        ->assertSee('20/11/2026 04:30 pm')
        ->assertSee('20/11/2026 05:05 pm')
        ->assertDontSee('09:23 pm');

    Livewire::actingAs($user)->test(SurveyRegistrosIndex::class)
        ->assertSee('20/11/2026 04:23 p. m.')
        ->assertDontSee('09:23 p. m.');

    // 16:23 en Colombia equivale a 21:23 UTC.
    expect($survey->fresh()->fecha_registro->utc()->format('H:i'))->toBe('21:23');
});

test('un registro de las 7:30 p. m. en Colombia se muestra en su día y no en el siguiente', function () {
    [$user, $survey] = coordinadorConDetalle(['fecha_registro' => '2026-11-20 19:30:00']);

    $this->actingAs($user)->get(route('operaciones.survey-auditoria.show', $survey))
        ->assertOk()->assertSee('20/11/2026 07:30 pm');
});
