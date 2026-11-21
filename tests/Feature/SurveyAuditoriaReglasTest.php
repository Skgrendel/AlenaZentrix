<?php

use App\Models\Company;
use App\Models\SurveyAdjunto;
use App\Models\SurveyRegistro;
use App\Services\survey123\AuditoriaSurveyRegistro;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Estas pruebas verifican la gravedad original de cada regla; los valores sugeridos de la empresa se prueban aparte.
beforeEach(fn () => config(['survey_auditoria.pesos_sugeridos' => []]));

/** @param  array<string, int>  $porCampo */
function agregarFotos(SurveyRegistro $registro, array $porCampo = ['foto_fachada' => 3, 'foto_medidor' => 1, 'foto_actividad' => 1]): void
{
    foreach ($porCampo as $campo => $cantidad) {
        for ($i = 1; $i <= $cantidad; $i++) {
            SurveyAdjunto::query()->create([
                'survey_registro_id' => $registro->id,
                'attachment_id' => random_int(1, 10_000_000),
                'campo' => $campo,
                'nombre' => "{$campo}-{$i}.jpg",
                'hash' => sha1($registro->id.$campo.$i),
            ]);
        }
    }
}

/** @return array<int, string> reglas con hallazgo */
function reglasDe(SurveyRegistro $registro): array
{
    return collect(app(AuditoriaSurveyRegistro::class)->auditar($registro->fresh()))->pluck('regla')->all();
}

test('un registro completo y coherente no tiene hallazgos', function () {
    $registro = SurveyRegistro::factory()->create();
    agregarFotos($registro);

    expect(app(AuditoriaSurveyRegistro::class)->auditar($registro->fresh()))->toBe([]);
});

test('la sonda con serie de sonda y Caps en cero es un error', function () {
    $registro = SurveyRegistro::factory()->create([
        'metodo_suspension' => 'sonda_cts', 'serie_sonda' => '0', 'serie_caps' => '0',
        'justificacion' => 'Servicio suspendido con dispositivo cts',
    ]);
    agregarFotos($registro);

    $hallazgos = collect(app(AuditoriaSurveyRegistro::class)->auditar($registro->fresh()));

    expect($hallazgos->where('regla', 'serie_sonda_caps')->where('severidad', 'error'))->toHaveCount(2);
});

test('faltan fotos de fachada y de medidor cuando la suspensión se ejecutó', function () {
    $registro = SurveyRegistro::factory()->create();
    agregarFotos($registro, ['foto_fachada' => 1]);

    $mensajes = collect(app(AuditoriaSurveyRegistro::class)->auditar($registro->fresh()))
        ->where('regla', 'fotos_minimas')->pluck('campo')->all();

    expect($mensajes)->toContain('foto_fachada', 'foto_medidor', 'foto_actividad');
});

test('las fotos repetidas dentro del mismo registro son un error', function () {
    $registro = SurveyRegistro::factory()->create();
    agregarFotos($registro);

    foreach (['a.jpg', 'b.jpg'] as $nombre) {
        SurveyAdjunto::query()->create([
            'survey_registro_id' => $registro->id, 'attachment_id' => random_int(1, 10_000_000),
            'campo' => 'foto_fachada', 'nombre' => $nombre, 'hash' => 'mismo-hash',
        ]);
    }

    expect(reglasDe($registro))->toContain('fotos_repetidas');
});

test('reutilizar una foto de otra visita es un error', function () {
    $original = SurveyRegistro::factory()->create(['company_id' => ($c = Company::factory()->create())->id, 'orden_trabajo' => '319000001']);
    $registro = SurveyRegistro::factory()->create(['company_id' => $c->id]);
    agregarFotos($registro);

    SurveyAdjunto::query()->create([
        'survey_registro_id' => $original->id, 'attachment_id' => 1, 'campo' => 'foto_medidor', 'nombre' => 'x.jpg', 'hash' => 'foto-compartida',
    ]);
    SurveyAdjunto::query()->create([
        'survey_registro_id' => $registro->id, 'attachment_id' => 2, 'campo' => 'foto_medidor', 'nombre' => 'y.jpg', 'hash' => 'foto-compartida',
    ]);

    $mensajes = collect(app(AuditoriaSurveyRegistro::class)->auditar($registro->fresh()))->pluck('mensaje')->implode(' ');

    expect($mensajes)->toContain('OT 319000001');
});

test('dos contratos distintos en el mismo punto generan una alerta', function () {
    $empresa = Company::factory()->create();
    SurveyRegistro::factory()->create([
        'company_id' => $empresa->id, 'contrato' => '336199', 'orden_trabajo' => '319923412',
        'latitud' => 10.3747867, 'longitud' => -75.4658333,
    ]);
    $segundo = SurveyRegistro::factory()->create([
        'company_id' => $empresa->id, 'contrato' => '489559',
        'latitud' => 10.37469, 'longitud' => -75.46565,
    ]);
    agregarFotos($segundo);

    $alerta = collect(app(AuditoriaSurveyRegistro::class)->auditar($segundo->fresh()))->firstWhere('regla', 'punto_repetido');

    expect($alerta['severidad'])->toBe('alerta')
        ->and($alerta['mensaje'])->toContain('OT 319923412');
});

test('la justificación que describe otro método que el registrado es una alerta', function () {
    $registro = SurveyRegistro::factory()->create([
        'metodo_suspension' => 'taponamiento_acometida',
        'justificacion' => 'Se realiza suspension desde acometida cts',
    ]);
    agregarFotos($registro);

    expect(reglasDe($registro))->toContain('justificacion');
});

test('una suspensión no ejecutada exige el motivo y la justificación', function () {
    $registro = SurveyRegistro::factory()->create([
        'suspension_ejecutada' => false, 'metodo_suspension' => null,
        'motivo_no_ejecucion' => null, 'justificacion' => null,
    ]);
    agregarFotos($registro, ['foto_fachada' => 3]);

    $campos = collect(app(AuditoriaSurveyRegistro::class)->auditar($registro->fresh()))->where('severidad', 'error')->pluck('campo')->all();

    expect($campos)->toContain('motivo_no_ejecucion', 'justificacion')
        ->and($campos)->not->toContain('foto_medidor');
});

test('las coordenadas fuera de Colombia o vacías son un error', function () {
    $fuera = SurveyRegistro::factory()->create(['latitud' => 40.4, 'longitud' => -3.7]);
    $vacia = SurveyRegistro::factory()->create(['latitud' => null, 'longitud' => null]);

    expect(reglasDe($fuera))->toContain('ubicacion')
        ->and(reglasDe($vacia))->toContain('ubicacion');
});

test('la lectura y la serie vacías son errores cuando se ejecutó la suspensión', function () {
    $registro = SurveyRegistro::factory()->create(['serie_medidor' => null, 'lectura_medidor' => null]);
    agregarFotos($registro);

    $campos = collect(app(AuditoriaSurveyRegistro::class)->auditar($registro->fresh()))->where('severidad', 'error')->pluck('campo')->all();

    expect($campos)->toContain('serie_medidor', 'lectura_medidor');
});

test('una regla apagada en la configuración no genera hallazgos', function () {
    config(['survey_auditoria.reglas.ubicacion' => false]);
    $registro = SurveyRegistro::factory()->create(['latitud' => null, 'longitud' => null]);
    agregarFotos($registro);

    expect(reglasDe($registro))->not->toContain('ubicacion');
});

test('auditarYGuardar guarda los hallazgos y los totales', function () {
    $registro = SurveyRegistro::factory()->create([
        'metodo_suspension' => 'sonda_ips', 'serie_sonda' => '0', 'serie_caps' => '0',
        'justificacion' => 'Servicio suspendido con sonda ips',
    ]);
    agregarFotos($registro, ['foto_fachada' => 1, 'foto_medidor' => 1, 'foto_actividad' => 1]);

    app(AuditoriaSurveyRegistro::class)->auditarYGuardar($registro);

    $registro = $registro->fresh();

    expect($registro->total_errores)->toBe(3)
        ->and($registro->total_alertas)->toBe(0)
        ->and($registro->hallazgos)->toHaveCount(3)
        ->and($registro->auditado_en)->not->toBeNull();
});
