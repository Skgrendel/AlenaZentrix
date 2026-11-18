<?php

use App\Enums\EstadoAuditoria;
use App\Enums\EstadoOrden;
use App\Enums\TipoServicio;
use App\Livewire\Auditor\AuditoriaRevisar;
use App\Livewire\Coordinador\ReporteRevisar;
use App\Models\auditoria;
use App\Models\OrdenJob;
use App\Models\reportes;
use App\Models\TipoAnomalia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $datos
 * @return array{0: User, 1: OrdenJob, 2: reportes}
 */
function crearReporteSuspensiones(array $datos): array
{
    $tecnico = User::factory()->create();

    $orden = OrdenJob::factory()->create([
        'company_id' => $tecnico->company_id,
        'personals_id' => $tecnico->personals_id,
        'tipo_servicio' => TipoServicio::AuditoriaCampo->value,
        'tipo_auditoria_campo' => 'visita_calidad_suspensiones',
        'estado' => EstadoOrden::EnRevision->value,
    ]);

    $reporte = reportes::factory()->create([
        'orden_job_id' => $orden->id,
        'personals_id' => $tecnico->personals_id,
        'numero_orden' => $orden->numero_orden,
        'company_id' => $tecnico->company_id,
        'imagenes' => [],
        'datos_servicio' => $datos,
    ]);

    return [$tecnico, $orden, $reporte];
}

it('renderiza suspensión ejecutada con método, series y fotos en ambas pantallas', function () {
    [$user, $orden, $reporte] = crearReporteSuspensiones([
        'categoria_campo' => 'residencial',
        'suspension_ejecutada' => true,
        'metodo_suspension' => 'sonda_ips',
        'serie_sonda' => 'SONDA-998',
        'serie_caps' => 'CAPS-445',
        'lectura_medidor' => 'LECT-1234',
        'anomalias_medidor' => [],
        'condicion_sellos' => 'SELLOS-ROTOS',
        'elementos_rotos' => 'ELEM-VIDRIO',
        'fotos_medidor' => ['reportes/med-1.jpg', 'reportes/med-2.jpg', 'reportes/med-3.jpg'],
        'fotos_fachada' => ['reportes/fachada-1.jpg', 'livewire-file:abc'],
        'fotos_suspension' => ['reportes/susp-1.jpg', 'reportes/susp-2.jpg'],
    ]);

    $anomalia = TipoAnomalia::create(['nombre' => 'ANOM-TEXTO', 'activo' => true, 'company_id' => $user->company_id]);
    $reporte->update(['datos_servicio' => [...$reporte->datos_servicio, 'anomalias_medidor' => [$anomalia->id]]]);

    $this->actingAs($user);

    $coordinador = Livewire::test(ReporteRevisar::class, ['orden' => $orden]);
    $display = $coordinador->instance()->datosServicioDisplay();

    expect($display['es_suspensiones'])->toBeTrue()
        ->and($display['subtipo'])->toBe('Visita de Calidad — Suspensiones')
        ->and($display['metodo_suspension'])->toBe('Sonda (Dispositivo IPS)')
        ->and($coordinador->instance()->fotosFachadaUrls())->toHaveCount(1)
        ->and($coordinador->instance()->fotosSuspensionUrls())->toHaveCount(2);

    $coordinador
        ->assertSee('Visita de Calidad — Suspensiones')
        ->assertSee('Residencial')
        ->assertSee('Sonda (Dispositivo IPS)')
        ->assertSee('SONDA-998')
        ->assertSee('CAPS-445')
        ->assertSee('Fotos de Fachada (1 foto)')
        ->assertSee('Fotos de Suspensión ejecutada (2 fotos)')
        ->assertSee('Estado del medidor')
        ->assertSee('LECT-1234')
        ->assertSee('ANOM-TEXTO')
        ->assertSee('SELLOS-ROTOS')
        ->assertSee('ELEM-VIDRIO')
        ->assertSee('Fotos del medidor (3 fotos)')
        ->assertDontSee('Justificación de no ejecución')
        ->assertDontSee('Autorización y Firmas')
        ->assertDontSee('livewire-file:');

    $auditoriaRecord = auditoria::create([
        'reportes_id' => $reporte->id,
        'estado' => EstadoAuditoria::Confirmado->value,
        'evidencias' => [],
        'fecha_auditoria' => now(),
    ]);

    Livewire::test(AuditoriaRevisar::class, ['auditoria' => $auditoriaRecord])
        ->assertSee('Visita de Calidad — Suspensiones')
        ->assertSee('Sonda (Dispositivo IPS)')
        ->assertSee('SONDA-998')
        ->assertSee('CAPS-445')
        ->assertSee('Fotos de Fachada (1 foto)')
        ->assertSee('Fotos de Suspensión ejecutada (2 fotos)')
        ->assertSee('Estado del medidor')
        ->assertSee('LECT-1234')
        ->assertSee('ANOM-TEXTO')
        ->assertSee('SELLOS-ROTOS')
        ->assertSee('ELEM-VIDRIO')
        ->assertSee('Fotos del medidor (3 fotos)')
        ->assertDontSee('Justificación de no ejecución')
        ->assertDontSee('Autorización y Firmas')
        ->assertDontSee('livewire-file:');
});

it('renderiza suspensión no ejecutada con el motivo en ambas pantallas', function () {
    [$user, $orden, $reporte] = crearReporteSuspensiones([
        'categoria_campo' => 'comercial',
        'suspension_ejecutada' => false,
        'motivo_no_ejecucion' => 'predio_cerrado',
        'justificacion_no_ejecucion' => 'JUSTIF-PORTON',
        'fotos_no_ejecucion' => ['reportes/causal-1.jpg', 'reportes/causal-2.jpg'],
        'fotos_fachada' => ['reportes/fachada-1.jpg'],
        'fotos_suspension' => [],
    ]);

    $this->actingAs($user);

    Livewire::test(ReporteRevisar::class, ['orden' => $orden])
        ->assertSee('Comercial')
        ->assertSee('Predio cerrado o desocupado')
        ->assertDontSee('Serie sonda')
        ->assertSee('Fotos de Fachada (1 foto)')
        ->assertDontSee('Fotos de Suspensión ejecutada')
        ->assertDontSee('Estado del medidor')
        ->assertDontSee('Observaciones del medidor')
        ->assertDontSee('Lectura de referencia')
        ->assertSee('Justificación de no ejecución')
        ->assertSee('JUSTIF-PORTON')
        ->assertSee('Evidencia de la causal (2 fotos)');

    $auditoriaRecord = auditoria::create([
        'reportes_id' => $reporte->id,
        'estado' => EstadoAuditoria::Confirmado->value,
        'evidencias' => [],
        'fecha_auditoria' => now(),
    ]);

    Livewire::test(AuditoriaRevisar::class, ['auditoria' => $auditoriaRecord])
        ->assertSee('Predio cerrado o desocupado')
        ->assertDontSee('Serie sonda')
        ->assertSee('Fotos de Fachada (1 foto)')
        ->assertDontSee('Fotos de Suspensión ejecutada')
        ->assertDontSee('Estado del medidor')
        ->assertDontSee('Observaciones del medidor')
        ->assertDontSee('Lectura de referencia')
        ->assertSee('Justificación de no ejecución')
        ->assertSee('JUSTIF-PORTON')
        ->assertSee('Evidencia de la causal (2 fotos)');
});

it('muestra el badge de verificación de lectura según la referencia de la orden', function (?string $referencia, string $badge) {
    [$user, $orden, $reporte] = crearReporteSuspensiones([
        'categoria_campo' => 'residencial',
        'suspension_ejecutada' => true,
        'metodo_suspension' => 'sonda_ips',
        'serie_sonda' => 'S-1',
        'serie_caps' => 'C-1',
        'lectura_medidor' => '1234',
        'anomalias_medidor' => [],
        'condicion_sellos' => 'Intactos',
        'elementos_rotos' => 'Ninguno',
        'fotos_medidor' => ['reportes/med-1.jpg'],
        'fotos_fachada' => ['reportes/fachada-1.jpg'],
        'fotos_suspension' => ['reportes/susp-1.jpg'],
    ]);

    $orden->forceFill(['lectura_referencia' => $referencia])->save();

    $this->actingAs($user);

    Livewire::test(ReporteRevisar::class, ['orden' => $orden])
        ->assertSee('Lectura de referencia')
        ->assertSee('Lectura registrada')
        ->assertSee($badge);

    $auditoriaRecord = auditoria::create([
        'reportes_id' => $reporte->id,
        'estado' => EstadoAuditoria::Confirmado->value,
        'evidencias' => [],
        'fecha_auditoria' => now(),
    ]);

    Livewire::test(AuditoriaRevisar::class, ['auditoria' => $auditoriaRecord])
        ->assertSee('Lectura de referencia')
        ->assertSee('Lectura registrada')
        ->assertSee($badge);
})->with([
    'coincide' => ['1234', 'Coincide'],
    'no coincide' => ['9999', 'No coincide — revisar'],
    'sin referencia' => [null, 'Sin referencia'],
]);

it('muestra las observaciones del medidor cuando la suspensión no fue ejecutada', function () {
    [$user, $orden, $reporte] = crearReporteSuspensiones([
        'categoria_campo' => 'comercial',
        'suspension_ejecutada' => false,
        'motivo_no_ejecucion' => 'riesgo_sitio',
        'justificacion_no_ejecucion' => 'JUSTIF-PERRO',
        'anomalias_medidor' => [],
        'condicion_sellos' => 'SELLOS-NOEJ',
        'elementos_rotos' => 'ELEM-NOEJ',
        'fotos_no_ejecucion' => ['reportes/causal-1.jpg'],
        'fotos_fachada' => ['reportes/fachada-1.jpg'],
    ]);

    $activa = TipoAnomalia::create(['nombre' => 'ANOM-NOEJ-A', 'activo' => true, 'company_id' => $user->company_id]);
    $eliminada = TipoAnomalia::create(['nombre' => 'ANOM-NOEJ-B', 'activo' => true, 'company_id' => $user->company_id]);
    $eliminada->delete();
    $reporte->update(['datos_servicio' => [...$reporte->datos_servicio, 'anomalias_medidor' => [$activa->id, $eliminada->id, 'id-inexistente']]]);

    $this->actingAs($user);

    $coordinador = Livewire::test(ReporteRevisar::class, ['orden' => $orden]);

    expect($coordinador->instance()->datosServicioDisplay())
        ->anomalias_medidor->toBe(['ANOM-NOEJ-A', 'ANOM-NOEJ-B'])
        ->condicion_sellos->toBe('SELLOS-NOEJ')
        ->elementos_rotos->toBe('ELEM-NOEJ');

    $coordinador
        ->assertSee('Observaciones del medidor')
        ->assertSee('ANOM-NOEJ-A')
        ->assertSee('ANOM-NOEJ-B')
        ->assertSee('SELLOS-NOEJ')
        ->assertSee('ELEM-NOEJ')
        ->assertDontSee('Lectura de referencia')
        ->assertDontSee('Fotos del medidor');

    $auditoriaRecord = auditoria::create([
        'reportes_id' => $reporte->id,
        'estado' => EstadoAuditoria::Confirmado->value,
        'evidencias' => [],
        'fecha_auditoria' => now(),
    ]);

    Livewire::test(AuditoriaRevisar::class, ['auditoria' => $auditoriaRecord])
        ->assertSee('Observaciones del medidor')
        ->assertSee('ANOM-NOEJ-A')
        ->assertSee('ANOM-NOEJ-B')
        ->assertSee('SELLOS-NOEJ')
        ->assertSee('ELEM-NOEJ')
        ->assertDontSee('Lectura de referencia')
        ->assertDontSee('Fotos del medidor');
});
