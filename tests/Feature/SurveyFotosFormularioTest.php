<?php

use App\Models\SurveyAdjunto;
use App\Models\SurveyRegistro;
use App\Models\User;
use App\Services\survey123\AuditoriaSurveyRegistro;
use App\Services\survey123\RequisitosDeFotos;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

const ANTES_DEL_CAMBIO = '2026-11-10 12:00:00';
const DESPUES_DEL_CAMBIO = '2026-11-18 02:05:00';

/** @param  array<string, int>  $fotos campo => cantidad */
function registroConFotos(string $fecha, array $fotos, array $registro = []): SurveyRegistro
{
    $r = SurveyRegistro::factory()->create($registro + ['fecha_registro' => $fecha, 'suspension_ejecutada' => true]);

    foreach ($fotos as $campo => $cantidad) {
        for ($i = 1; $i <= $cantidad; $i++) {
            SurveyAdjunto::query()->create([
                'survey_registro_id' => $r->id, 'attachment_id' => random_int(1, 10_000_000), 'campo' => $campo,
                'nombre' => "{$campo}-{$i}.jpg", 'hash' => sha1($r->id.$campo.$i),
            ]);
        }
    }

    return $r->fresh();
}

/** @return array<int, array<string, string>> hallazgos de fotos */
function hallazgosDeFotos(SurveyRegistro $r): array
{
    return collect(app(AuditoriaSurveyRegistro::class)->auditar($r))->where('regla', 'fotos_minimas')->values()->all();
}

/** @return array<int, string> */
function mensajesDeFotos(SurveyRegistro $r, string $severidad = 'error'): array
{
    return collect(hallazgosDeFotos($r))->where('severidad', $severidad)->pluck('mensaje')->all();
}

$nuevoCompleto = ['foto_fachada' => 3, 'foto_medidor' => 1, 'foto_ivt' => 1, 'foto_actividad1' => 1, 'foto_actividad2' => 1, 'foto_actividad3' => 1];

test('un registro del formulario nuevo con todas las fotos no tiene hallazgos de fotos', function () use ($nuevoCompleto) {
    expect(hallazgosDeFotos(registroConFotos(DESPUES_DEL_CAMBIO, $nuevoCompleto)))->toBe([]);
});

test('al formulario nuevo le exige el IVT', function () use ($nuevoCompleto) {
    unset($nuevoCompleto['foto_ivt']);

    expect(mensajesDeFotos(registroConFotos(DESPUES_DEL_CAMBIO, $nuevoCompleto)))->toBe(['Falta la foto del IVT.']);
});

test('al formulario nuevo le exige la foto de la actividad antes, durante y después', function (string $campo, string $mensaje) use ($nuevoCompleto) {
    unset($nuevoCompleto[$campo]);

    expect(mensajesDeFotos(registroConFotos(DESPUES_DEL_CAMBIO, $nuevoCompleto)))->toBe([$mensaje]);
})->with([
    'antes' => ['foto_actividad1', 'Falta la foto de la actividad (antes).'],
    'durante' => ['foto_actividad2', 'Falta la foto de la actividad (durante).'],
    'después' => ['foto_actividad3', 'Falta la foto de la actividad (después).'],
]);

test('un registro posterior al cambio sin fotos de actividad ni IVT se evalúa como formulario nuevo', function () {
    $mensajes = mensajesDeFotos(registroConFotos(DESPUES_DEL_CAMBIO, ['foto_fachada' => 3, 'foto_medidor' => 1]));

    expect($mensajes)->toBe([
        'Falta la foto del IVT.',
        'Falta la foto de la actividad (antes).',
        'Falta la foto de la actividad (durante).',
        'Falta la foto de la actividad (después).',
    ]);
});

test('un registro del formulario anterior solo exige una foto de actividad y nunca el IVT', function () {
    $completo = registroConFotos(ANTES_DEL_CAMBIO, ['foto_fachada' => 3, 'foto_medidor' => 1, 'foto_actividad' => 1]);
    $sinActividad = registroConFotos(ANTES_DEL_CAMBIO, ['foto_fachada' => 3, 'foto_medidor' => 1]);

    expect(hallazgosDeFotos($completo))->toBe([])
        ->and(mensajesDeFotos($sinActividad))->toBe(['Hay 0 foto(s) de actividad y se requieren al menos 1.']);
});

test('un registro posterior al cambio enviado con la versión anterior se señala con una alerta, no con errores', function () {
    $registro = registroConFotos(DESPUES_DEL_CAMBIO, ['foto_fachada' => 3, 'foto_medidor' => 1, 'foto_actividad' => 1]);

    expect(mensajesDeFotos($registro))->toBe([])
        ->and(mensajesDeFotos($registro, 'alerta'))->toBe(['Se envió con una versión anterior del formulario: no trae la foto del IVT ni las de la actividad por momento.']);
});

test('si la suspensión no se ejecutó no se exigen ni el IVT ni la actividad', function () {
    $registro = registroConFotos(DESPUES_DEL_CAMBIO, ['foto_fachada' => 3], ['suspension_ejecutada' => false]);

    expect(mensajesDeFotos($registro))->toBe([])
        ->and(mensajesDeFotos($registro, 'alerta'))->toBe([]);
});

test('el cambio de formulario se decide por la fecha configurada, que está en UTC (13:21 UTC son las 08:21 en Colombia)', function () {
    $minuto = fn (string $fecha) => registroConFotos($fecha, ['foto_fachada' => 3, 'foto_medidor' => 1]);

    expect(mensajesDeFotos($minuto('2026-11-17 08:20:59')))->toBe(['Hay 0 foto(s) de actividad y se requieren al menos 1.'])
        ->and(mensajesDeFotos($minuto('2026-11-17 08:21:00')))->toContain('Falta la foto del IVT.');
});

test('con tener una sola foto nueva el registro se evalúa como formulario nuevo aunque sea anterior a la fecha', function () {
    $registro = registroConFotos(ANTES_DEL_CAMBIO, ['foto_fachada' => 3, 'foto_medidor' => 1, 'foto_ivt' => 1]);

    expect(mensajesDeFotos($registro))->toContain('Falta la foto de la actividad (antes).');
});

test('la fecha del cambio de formulario se puede ajustar en la configuración', function () {
    config(['survey_auditoria.parametros.formulario_nuevo_desde' => '2027-01-01 00:00:00']);

    $registro = registroConFotos(DESPUES_DEL_CAMBIO, ['foto_fachada' => 3, 'foto_medidor' => 1, 'foto_actividad' => 1]);

    expect(mensajesDeFotos($registro))->toBe([])
        ->and(mensajesDeFotos($registro, 'alerta'))->toBe([]);
});

test('RequisitosDeFotos entrega la lista del formulario nuevo con sus 6 filas', function () use ($nuevoCompleto) {
    $r = app(RequisitosDeFotos::class)->evaluar(registroConFotos(DESPUES_DEL_CAMBIO, $nuevoCompleto));

    expect($r['formulario_nuevo'])->toBeTrue()
        ->and($r['version_anterior'])->toBeFalse()
        ->and(array_column($r['requisitos'], 'etiqueta'))->toBe(['Fachada', 'Medidor', 'IVT', 'Actividad · antes', 'Actividad · durante', 'Actividad · después'])
        ->and(collect($r['requisitos'])->every(fn ($q) => $q['completa']))->toBeTrue();
});

test('RequisitosDeFotos entrega la lista del formulario anterior con sus 3 filas', function () {
    $r = app(RequisitosDeFotos::class)->evaluar(registroConFotos(ANTES_DEL_CAMBIO, ['foto_fachada' => 3, 'foto_medidor' => 1, 'foto_actividad' => 2]));

    expect($r['formulario_nuevo'])->toBeFalse()
        ->and(array_column($r['requisitos'], 'etiqueta'))->toBe(['Fachada', 'Medidor', 'Actividad (suspensión o novedad)']);
});

// ── Pantalla de detalle ──────────────────────────────────────────────────────

test('el detalle muestra la lista de verificación del formulario nuevo y las fotos con su etiqueta, en orden', function () use ($nuevoCompleto) {
    Storage::fake('s3');
    $this->seed(PermissionsSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('Coordinador');
    $registro = registroConFotos(DESPUES_DEL_CAMBIO, $nuevoCompleto, ['company_id' => $user->company_id]);

    // Se suben en orden inverso para comprobar que el detalle las ordena por tipo.
    $this->actingAs($user)
        ->get(route('operaciones.survey-auditoria.show', $registro))
        ->assertOk()
        ->assertSeeInOrder(['Fachada', 'Medidor', 'IVT', 'Actividad · antes', 'Actividad · durante', 'Actividad · después'])
        ->assertSee('Fotos del técnico (8)');
});

test('el detalle de un registro del formulario anterior no muestra IVT', function () {
    Storage::fake('s3');
    $this->seed(PermissionsSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('Coordinador');
    $registro = registroConFotos(ANTES_DEL_CAMBIO, ['foto_fachada' => 3, 'foto_medidor' => 1, 'foto_actividad' => 1], ['company_id' => $user->company_id]);

    $this->actingAs($user)
        ->get(route('operaciones.survey-auditoria.show', $registro))
        ->assertOk()
        ->assertSee('Actividad (suspensión o novedad)')
        ->assertDontSee('IVT');
});
