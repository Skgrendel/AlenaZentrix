<?php

use App\Enums\EstadoOrden;
use App\Enums\TipoServicio;
use App\Livewire\Auditor\AuditoriaRevisar;
use App\Livewire\Coordinador\ReporteRevisar;
use App\Livewire\Coordinador\ReportesVigilanciaIndex;
use App\Livewire\Informes\InformesIndex;
use App\Livewire\Tecnico\ReporteEdit;
use App\Models\auditoria;
use App\Models\OrdenJob;
use App\Models\reportes;
use App\Models\ubicacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('rechazo masivo de Vigilancia marca todos los turnos requiere_correccion y devuelve la orden a EnProgreso', function () {
    Mail::fake();
    $coordinador = User::factory()->create();
    $investigador = User::factory()->create(['company_id' => $coordinador->company_id]);

    $orden = OrdenJob::factory()->create([
        'company_id' => $coordinador->company_id,
        'personals_id' => $investigador->personals_id,
        'tipo_servicio' => TipoServicio::Vigilancia->value,
        'estado' => EstadoOrden::EnRevision->value,
    ]);

    $t1 = reportes::create([
        'orden_job_id' => $orden->id,
        'personals_id' => $investigador->personals_id,
        'company_id' => $coordinador->company_id,
        'numero_orden' => $orden->numero_orden,
        'datos_servicio' => ['fecha_turno' => '2026-10-01'],
    ]);

    $t2 = reportes::create([
        'orden_job_id' => $orden->id,
        'personals_id' => $investigador->personals_id,
        'company_id' => $coordinador->company_id,
        'numero_orden' => $orden->numero_orden,
        'datos_servicio' => ['fecha_turno' => '2026-10-02'],
    ]);

    Livewire::actingAs($coordinador)
        ->test(ReportesVigilanciaIndex::class, ['orden' => $orden])
        ->set('comentarios', 'Por favor detallar las novedades de ambos turnos.')
        ->call('rechazar')
        ->assertHasNoErrors();

    expect($orden->fresh()->estado)->toBe(EstadoOrden::EnProgreso)
        ->and($t1->fresh()->requiere_correccion)->toBeTrue()
        ->and($t2->fresh()->requiere_correccion)->toBeTrue()
        ->and($t1->fresh()->comentarios)->toBe('Por favor detallar las novedades de ambos turnos.');
});

test('fallback a comentario_usuario y comentario_tecnico de la orden en ReporteRevisar y AuditoriaRevisar', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $orden = OrdenJob::factory()->create([
        'company_id' => $user->company_id,
        'tipo_servicio' => TipoServicio::AuditoriaCampo->value,
        'tipo_auditoria_campo' => 'visita_calidad_emergencia',
        'estado' => EstadoOrden::EnRevision->value,
        'comentario_usuario' => 'Comentario usuario desde orden',
        'comentario_tecnico' => 'Comentario técnico desde orden',
    ]);

    $reporte = reportes::create([
        'orden_job_id' => $orden->id,
        'company_id' => $user->company_id,
        'numero_orden' => $orden->numero_orden,
        'datos_servicio' => [],
    ]);

    $aud = auditoria::create([
        'reportes_id' => $reporte->id,
    ]);

    $revisar = new ReporteRevisar;
    $revisar->orden = $orden;
    $revisar->reporte = $reporte;
    $datosCoord = $revisar->datosServicioDisplay();

    expect($datosCoord['comentario_usuario'])->toBe('Comentario usuario desde orden')
        ->and($datosCoord['comentario_tecnico'])->toBe('Comentario técnico desde orden');

    $audRevisar = new AuditoriaRevisar;
    $audRevisar->auditoria = $aud->load('reporte.ordenJob');
    $datosAud = $audRevisar->datosServicioDisplay();

    expect($datosAud['comentario_usuario'])->toBe('Comentario usuario desde orden')
        ->and($datosAud['comentario_tecnico'])->toBe('Comentario técnico desde orden');
});

test('ReporteEdit actualizarUbicacion no pisa coordenadas previas cuando latitud o longitud son nulas', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $ubic = ubicacion::create([
        'company_id' => $user->company_id,
        'latitud' => '4.60971',
        'longitud' => '-74.08175',
        'direccion' => 'Calle 100',
    ]);

    $orden = OrdenJob::factory()->create([
        'company_id' => $user->company_id,
        'personals_id' => $user->personals_id,
        'tipo_servicio' => TipoServicio::Investigacion->value,
        'estado' => EstadoOrden::Rechazada->value,
    ]);

    $reporte = reportes::create([
        'orden_job_id' => $orden->id,
        'personals_id' => $user->personals_id,
        'company_id' => $user->company_id,
        'numero_orden' => $orden->numero_orden,
        'ubicacions_id' => $ubic->id,
    ]);

    $component = new ReporteEdit;
    $component->orden = $orden;
    $component->reporte = $reporte;
    $component->latitud = null;
    $component->longitud = null;

    $method = new ReflectionMethod($component, 'actualizarUbicacion');
    $method->setAccessible(true);
    $method->invoke($component);

    $ubic->refresh();
    expect((string) $ubic->latitud)->toContain('4.60971')
        ->and((string) $ubic->longitud)->toContain('-74.08175');
});

test('InformesIndex expande todos los turnos de Vigilancia e incluye columnas de Investigacion, Legal y Riesgo', function () {
    $user = User::factory()->create();

    $ordenVig = OrdenJob::factory()->create([
        'company_id' => $user->company_id,
        'tipo_servicio' => TipoServicio::Vigilancia->value,
        'numero_orden' => 'ODT-VIG-100',
    ]);

    reportes::create([
        'orden_job_id' => $ordenVig->id,
        'company_id' => $user->company_id,
        'numero_orden' => $ordenVig->numero_orden,
        'datos_servicio' => ['fecha_turno' => '2026-10-01', 'hora_inicio' => '08:00', 'hora_fin' => '16:00', 'hay_novedad' => true],
    ]);

    reportes::create([
        'orden_job_id' => $ordenVig->id,
        'company_id' => $user->company_id,
        'numero_orden' => $ordenVig->numero_orden,
        'datos_servicio' => ['fecha_turno' => '2026-10-02', 'hora_inicio' => '16:00', 'hora_fin' => '23:00', 'hay_novedad' => false],
    ]);

    $preview = Livewire::actingAs($user)
        ->test(InformesIndex::class)
        ->set('servicio', TipoServicio::Vigilancia->value)
        ->call('generarVista')
        ->get('preview');

    expect($preview)->toHaveCount(2)
        ->and(collect($preview)->pluck('Fecha Turno')->all())->toContain('2026-10-01', '2026-10-02');
});
