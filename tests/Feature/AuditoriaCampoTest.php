<?php

use App\Enums\EstadoOrden;
use App\Enums\PrioridadOrden;
use App\Enums\TipoServicio;
use App\Livewire\Coordinador\ReporteRevisar;
use App\Models\Company;
use App\Models\OrdenJob;
use App\Models\reportes;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('AuditoriaCampo enum case exists with correct label and color', function () {
    $case = TipoServicio::AuditoriaCampo;
    expect($case->label())->toBe('Auditorías de Campo')
        ->and($case->color())->toBe('purple')
        ->and($case->tieneTecnicoAsignado())->toBeTrue();
});

it('can create an OrdenJob with tipo_auditoria_campo', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id, 'is_superadmin' => false]);
    $this->actingAs($user);

    $orden = OrdenJob::create([
        'tipo_servicio' => TipoServicio::AuditoriaCampo,
        'tipo_auditoria_campo' => 'visita_calidad_emergencia',
        'prioridad' => PrioridadOrden::Media,
        'estado' => EstadoOrden::Pendiente,
        'estado_servicio' => true,
    ]);

    expect($orden->tipo_auditoria_campo)->toBe('visita_calidad_emergencia')
        ->and($orden->tipo_servicio)->toBe(TipoServicio::AuditoriaCampo);
});

it('saves auditoria campo report with datos_servicio including signatures', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id, 'is_superadmin' => false]);
    $this->actingAs($user);

    $orden = OrdenJob::create([
        'tipo_servicio' => TipoServicio::AuditoriaCampo,
        'tipo_auditoria_campo' => 'visita_calidad_trabajos_varios',
        'prioridad' => PrioridadOrden::Media,
        'estado' => EstadoOrden::EnProgreso,
        'estado_servicio' => true,
    ]);

    $reporte = reportes::create([
        'orden_job_id' => $orden->id,
        'personals_id' => null,
        'numero_orden' => $orden->numero_orden,
        'datos_servicio' => [
            'nombre_atiende' => 'María García',
            'recibio_visita' => true,
            'autoriza' => true,
            'firma_usuario' => 'data:image/png;base64,abc123',
            'cedula_usuario' => '12345678',
            'firma_auditor' => 'data:image/png;base64,xyz789',
            'cedula_auditor' => '87654321',
        ],
    ]);

    expect($reporte->datos_servicio['nombre_atiende'])->toBe('María García')
        ->and($reporte->datos_servicio['firma_usuario'])->toStartWith('data:image/png;base64,')
        ->and($reporte->datos_servicio['autoriza'])->toBeTrue();
});

it('datosServicioDisplay returns structured array for AuditoriaCampo', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id, 'is_superadmin' => false]);
    $this->actingAs($user);

    $orden = OrdenJob::create([
        'tipo_servicio' => TipoServicio::AuditoriaCampo,
        'tipo_auditoria_campo' => 'visita_calidad_emergencia',
        'prioridad' => PrioridadOrden::Media,
        'estado' => EstadoOrden::EnRevision,
        'estado_servicio' => true,
    ]);

    $reporte = reportes::create([
        'orden_job_id' => $orden->id,
        'personals_id' => null,
        'numero_orden' => $orden->numero_orden,
        'datos_servicio' => ['autoriza' => true, 'nombre_atiende' => 'Test', 'recibio_visita' => true],
    ]);

    $component = new ReporteRevisar;
    $component->orden = $orden->load('personal');
    $component->reporte = $reporte;

    $display = $component->datosServicioDisplay();

    expect($display['subtipo'])->toBe('Visita de Calidad — Emergencia')
        ->and($display['nombre_atiende'])->toBe('Test')
        ->and($display['autoriza'])->toBeTrue();
});
