<?php

use App\Enums\TipoServicio;
use App\Models\Company;
use App\Models\OrdenJob;
use App\Models\reportes;
use App\Models\SurveyRegistro;
use App\Models\ubicacion;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Orden de auditoría con lo reportado por el técnico en Survey123 como referencia.
 *
 * @param  array<string, mixed>  $registro
 */
function ordenConReferencia(array $registro = []): OrdenJob
{
    $empresa = Company::factory()->create();

    $survey = SurveyRegistro::factory()->create($registro + [
        'company_id' => $empresa->id,
        'categoria' => 'residencial',
        'suspension_ejecutada' => true,
        'metodo_suspension' => 'taponamiento_acometida',
        'serie_medidor' => '981797',
        'lectura_medidor' => '6972',
        'latitud' => 10.407333,
        'longitud' => -75.488048,
    ]);

    return OrdenJob::factory()->create([
        'company_id' => $empresa->id,
        'tipo_servicio' => TipoServicio::AuditoriaCampo,
        'tipo_auditoria_campo' => 'visita_calidad_suspensiones',
        'survey_registro_id' => $survey->id,
        'lectura_referencia' => $survey->lectura_medidor,
        'serie_medidor_referencia' => $survey->serie_medidor,
        'latitud_referencia' => $survey->latitud,
        'longitud_referencia' => $survey->longitud,
    ]);
}

/**
 * Reporte del auditor; lo que no se indique coincide con lo reportado por el técnico.
 *
 * @param  array<string, mixed>  $datos
 */
function reporteDelAuditor(OrdenJob $orden, array $datos = [], float $latitud = 10.407333, float $longitud = -75.488048): reportes
{
    $punto = ubicacion::create(['latitud' => (string) $latitud, 'longitud' => (string) $longitud]);

    return reportes::create([
        'orden_job_id' => $orden->id,
        'numero_orden' => $orden->numero_orden,
        'ubicacions_id' => $punto->id,
        'datos_servicio' => $datos + [
            'categoria_campo' => 'residencial',
            'suspension_ejecutada' => true,
            'metodo_suspension' => 'taponamiento_acometida',
            'serie_medidor' => '981797',
            'lectura_medidor' => '6972',
        ],
    ])->fresh();
}

/** @return array<string, string> campo => resultado */
function resultadosPorCampo(reportes $reporte): array
{
    return collect($reporte->comparacion['filas'])->pluck('resultado', 'campo')->all();
}

test('todo coincide y el resultado es conforme', function () {
    $reporte = reporteDelAuditor(ordenConReferencia(), latitud: 10.4074);

    expect($reporte->resultado_comparacion)->toBe('conforme')
        ->and($reporte->comparacion['alertas'])->toBe(0)
        ->and(resultadosPorCampo($reporte))->toMatchArray([
            'ejecucion' => 'coincide', 'metodo' => 'coincide', 'serie_medidor' => 'coincide',
            'lectura' => 'coincide', 'ubicacion' => 'coincide', 'categoria' => 'coincide',
        ]);
});

test('una lectura distinta es no conforme', function () {
    $reporte = reporteDelAuditor(ordenConReferencia(), ['lectura_medidor' => '6980']);

    expect($reporte->resultado_comparacion)->toBe('no_conforme')
        ->and(resultadosPorCampo($reporte)['lectura'])->toBe('no_coincide');
});

test('una serie de medidor distinta es no conforme', function () {
    $reporte = reporteDelAuditor(ordenConReferencia(), ['serie_medidor' => '111111']);

    expect($reporte->resultado_comparacion)->toBe('no_conforme')
        ->and(resultadosPorCampo($reporte)['serie_medidor'])->toBe('no_coincide');
});

test('un método distinto es no conforme', function () {
    $reporte = reporteDelAuditor(ordenConReferencia(), [
        'metodo_suspension' => 'sonda_cts', 'serie_sonda' => '5', 'serie_caps' => '6',
    ]);

    expect($reporte->resultado_comparacion)->toBe('no_conforme')
        ->and(resultadosPorCampo($reporte)['metodo'])->toBe('no_coincide');
});

test('una ubicación a más de 200 metros es no conforme y se informa la distancia', function () {
    // 0,002 grados de latitud son unos 222 m.
    $reporte = reporteDelAuditor(ordenConReferencia(), latitud: 10.409333);

    $fila = collect($reporte->comparacion['filas'])->firstWhere('campo', 'ubicacion');

    expect($reporte->resultado_comparacion)->toBe('no_conforme')
        ->and($fila['resultado'])->toBe('no_coincide')
        ->and($fila['detalle'])->toContain('máximo 200 m');
});

test('una ubicación a menos de 200 metros se considera el mismo sitio', function () {
    // 0,001 grados de latitud son unos 111 m: antes (límite de 50 m) era no conforme.
    $reporte = reporteDelAuditor(ordenConReferencia(), latitud: 10.408333);

    $fila = collect($reporte->comparacion['filas'])->firstWhere('campo', 'ubicacion');

    expect($fila['resultado'])->toBe('coincide')
        ->and($fila['detalle'])->toContain('máximo 200 m');
});

test('una categoría distinta solo genera alerta', function () {
    $reporte = reporteDelAuditor(ordenConReferencia(), ['categoria_campo' => 'comercial']);

    expect($reporte->resultado_comparacion)->toBe('conforme')
        ->and($reporte->comparacion['alertas'])->toBe(1)
        ->and(resultadosPorCampo($reporte)['categoria'])->toBe('no_coincide');
});

test('las series de sonda y Caps distintas solo generan alerta', function () {
    $orden = ordenConReferencia(['metodo_suspension' => 'sonda_cts', 'serie_sonda' => '12345', 'serie_caps' => '67890']);

    $reporte = reporteDelAuditor($orden, [
        'metodo_suspension' => 'sonda_cts', 'serie_sonda' => '99999', 'serie_caps' => '67890',
    ]);

    expect($reporte->resultado_comparacion)->toBe('conforme')
        ->and(resultadosPorCampo($reporte))->toMatchArray(['serie_sonda' => 'no_coincide', 'serie_caps' => 'coincide'])
        ->and($reporte->comparacion['alertas'])->toBe(1);
});

test('si el auditor no pudo entrar al predio el resultado es no verificable', function () {
    $reporte = reporteDelAuditor(ordenConReferencia(), [
        'suspension_ejecutada' => false, 'motivo_no_ejecucion' => 'predio_cerrado',
        'metodo_suspension' => null, 'serie_medidor' => null, 'lectura_medidor' => null,
    ]);

    expect($reporte->resultado_comparacion)->toBe('no_verificable');
});

test('si el técnico reportó ejecutada y el auditor la encontró sin suspender es no conforme', function () {
    $reporte = reporteDelAuditor(ordenConReferencia(), [
        'suspension_ejecutada' => false, 'motivo_no_ejecucion' => 'usuario_taponado',
        'metodo_suspension' => null, 'serie_medidor' => null, 'lectura_medidor' => null,
    ]);

    expect($reporte->resultado_comparacion)->toBe('no_conforme')
        ->and(resultadosPorCampo($reporte)['ejecucion'])->toBe('no_coincide');
});

test('sin coordenadas del auditor la ubicación queda sin dato y no bloquea', function () {
    $orden = ordenConReferencia();
    $reporte = reportes::create([
        'orden_job_id' => $orden->id,
        'numero_orden' => $orden->numero_orden,
        'datos_servicio' => ['categoria_campo' => 'residencial', 'suspension_ejecutada' => true, 'metodo_suspension' => 'taponamiento_acometida', 'serie_medidor' => '981797', 'lectura_medidor' => '6972'],
    ])->fresh();

    expect($reporte->resultado_comparacion)->toBe('conforme')
        ->and(resultadosPorCampo($reporte)['ubicacion'])->toBe('sin_dato')
        ->and($reporte->comparacion['alertas'])->toBe(1);
});

test('una orden sin referencia no se compara', function () {
    $orden = OrdenJob::factory()->create([
        'tipo_servicio' => TipoServicio::AuditoriaCampo,
        'tipo_auditoria_campo' => 'visita_calidad_suspensiones',
    ]);

    $reporte = reporteDelAuditor($orden);

    expect($reporte->resultado_comparacion)->toBeNull();
});

test('otros subtipos de auditoría no se comparan', function () {
    $orden = OrdenJob::factory()->create([
        'tipo_servicio' => TipoServicio::AuditoriaCampo,
        'tipo_auditoria_campo' => 'visita_calidad_emergencia',
        'lectura_referencia' => '100',
    ]);

    $reporte = reporteDelAuditor($orden);

    expect($reporte->comparacion)->toBeNull();
});

test('al corregir el reporte la comparación se recalcula', function () {
    $reporte = reporteDelAuditor(ordenConReferencia(), ['lectura_medidor' => '7000']);
    expect($reporte->resultado_comparacion)->toBe('no_conforme');

    $reporte->update(['datos_servicio' => [...$reporte->datos_servicio, 'lectura_medidor' => '6972']]);

    expect($reporte->fresh()->resultado_comparacion)->toBe('conforme');
});
