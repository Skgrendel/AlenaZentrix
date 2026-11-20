<?php

use App\Models\personals;
use App\Models\SurveyRegistro;
use App\Services\coordinador\AsignarAuditoriaSurveyService;
use App\Services\coordinador\DataGisServices;
use App\Services\survey123\ConsultaGisContrato;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** @return array<string, mixed> */
function respuestaGis(array $cambios = []): array
{
    return array_replace_recursive([
        'titular' => ['nombre' => 'MARIA PEREZ'],
        'ubicacion' => ['direccion' => 'CALLE 10 # 5-20', 'barrio' => 'VILLA SOL', 'municipio' => 'CARTAGENA'],
    ], $cambios);
}

function simularGis(array|string $respuesta): void
{
    test()->mock(DataGisServices::class, function ($mock) use ($respuesta): void {
        $mock->shouldReceive('consultarContrato')->andReturn(is_string($respuesta) ? ['error' => $respuesta] : $respuesta);
    });
}

test('devuelve nombre, dirección y barrio del contrato según el GIS', function () {
    simularGis(respuestaGis());

    expect(app(ConsultaGisContrato::class)->datosBasicos('489559'))->toBe([
        'nombre' => 'MARIA PEREZ', 'direccion' => 'CALLE 10 # 5-20', 'barrio' => 'VILLA SOL',
    ]);
});

test('si el GIS no trae barrio usa el municipio', function () {
    simularGis(respuestaGis(['ubicacion' => ['barrio' => null]]));

    expect(app(ConsultaGisContrato::class)->datosBasicos('489559')['barrio'])->toBe('CARTAGENA');
});

test('devuelve null si el contrato no existe, el GIS falla o no hay contrato', function (?string $contrato, array|string $gis) {
    simularGis($gis);

    expect(app(ConsultaGisContrato::class)->datosBasicos($contrato))->toBeNull();
})->with([
    'no existe' => ['489559', 'No se encontraron datos para el contrato proporcionado.'],
    'el GIS falla' => ['489559', 'Se produjo un error al intentar acceder al servicio GIS.'],
    'sin contrato' => [null, []],
]);

test('al asignar, la orden se completa con el nombre, la dirección y el barrio del GIS', function () {
    simularGis(respuestaGis());
    $registro = SurveyRegistro::factory()->create();
    $auditor = personals::factory()->create(['company_id' => $registro->company_id]);

    $orden = app(AsignarAuditoriaSurveyService::class)->asignar($registro, $auditor->id);

    expect($orden->cliente)->toBe('MARIA PEREZ')
        ->and($orden->direccion)->toBe('CALLE 10 # 5-20')
        ->and($orden->barrio)->toBe('VILLA SOL');
});

test('si el GIS no responde, la orden se asigna igual sin los datos del usuario', function () {
    simularGis('Se produjo un error al intentar acceder al servicio GIS.');
    $registro = SurveyRegistro::factory()->create();
    $auditor = personals::factory()->create(['company_id' => $registro->company_id]);

    $orden = app(AsignarAuditoriaSurveyService::class)->asignar($registro, $auditor->id);

    expect($orden->cliente)->toBeNull()
        ->and($orden->direccion)->toBeNull()
        ->and($orden->personals_id)->toBe($auditor->id);
});

test('si la consulta al GIS lanza una excepción, la orden se asigna igual', function () {
    test()->mock(DataGisServices::class, function ($mock): void {
        $mock->shouldReceive('consultarContrato')->andThrow(new RuntimeException('sin red'));
    });
    $registro = SurveyRegistro::factory()->create();
    $auditor = personals::factory()->create(['company_id' => $registro->company_id]);

    $orden = app(AsignarAuditoriaSurveyService::class)->asignar($registro, $auditor->id);

    expect($orden->cliente)->toBeNull()
        ->and($orden->personals_id)->toBe($auditor->id);
});
