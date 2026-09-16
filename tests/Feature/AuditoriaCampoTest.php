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

it('datosServicioDisplay returns medidores digitales fields for that subtipo', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id, 'is_superadmin' => false]);
    $this->actingAs($user);

    $orden = OrdenJob::create([
        'tipo_servicio' => TipoServicio::AuditoriaCampo,
        'tipo_auditoria_campo' => 'visita_calidad_medidores_digitales',
        'prioridad' => PrioridadOrden::Media,
        'estado' => EstadoOrden::EnRevision,
        'estado_servicio' => true,
    ]);

    $reporte = reportes::create([
        'orden_job_id' => $orden->id,
        'personals_id' => null,
        'numero_orden' => $orden->numero_orden,
        'datos_servicio' => [
            'fecha_visita_medidor' => '2026-05-29',
            'tecnico_atendio_medidor' => 'Juan Pérez',
            'nombre_atiende_medidor' => 'María García',
            'numero_medidor_instalado' => 'MD-123456',
            'telefono_contacto_medidor' => '3001234567',
            'resultado_medidor' => 'conforme',
            'informo_gratuidad_medidor' => true,
            'solicito_dinero_medidor' => false,
            'gestion_conforme_medidor' => true,
            'requiere_seguimiento_medidor' => false,
            'hallazgos_medidor' => 'Sin novedades',
            'autoriza' => true,
        ],
    ]);

    $component = new ReporteRevisar;
    $component->orden = $orden->load('personal');
    $component->reporte = $reporte;

    $display = $component->datosServicioDisplay();

    expect($display['subtipo'])->toBe('Visita de Calidad — Medidores Digitales')
        ->and($display['es_medidores_digitales'])->toBeTrue()
        ->and($display['tecnico_atendio_medidor'])->toBe('Juan Pérez')
        ->and($display['nombre_atiende_medidor'])->toBe('María García')
        ->and($display['numero_medidor_instalado'])->toBe('MD-123456')
        ->and($display['telefono_contacto_medidor'])->toBe('3001234567')
        ->and($display['resultado_medidor'])->toBe('Conforme')
        ->and($display['informo_gratuidad_medidor'])->toBeTrue()
        ->and($display['solicito_dinero_medidor'])->toBeFalse()
        ->and($display['hallazgos_medidor'])->toBe('Sin novedades')
        ->and($display)->not->toHaveKey('se_detecto_fuga')
        ->and($display)->not->toHaveKey('se_usaron_materiales');
});

it('evidenciasUrls clasifies images and documents by extension', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id, 'is_superadmin' => false]);
    $this->actingAs($user);

    $orden = OrdenJob::create([
        'tipo_servicio' => TipoServicio::Gestion_Riesgo,
        'prioridad' => PrioridadOrden::Media,
        'estado' => EstadoOrden::EnRevision,
        'estado_servicio' => true,
    ]);

    $reporte = reportes::create([
        'orden_job_id' => $orden->id,
        'personals_id' => null,
        'numero_orden' => $orden->numero_orden,
        'datos_servicio' => [
            'urls_evidencia' => [
                'https://example.test/evidencias/foto-1.png',
                'https://example.test/evidencias/manual.pdf',
            ],
        ],
    ]);

    $component = new ReporteRevisar;
    $component->orden = $orden->load('personal');
    $component->reporte = $reporte;

    $evidencias = $component->evidenciasUrls();

    expect($evidencias)->toHaveCount(2)
        ->and($evidencias[0]['es_imagen'])->toBeTrue()
        ->and($evidencias[0]['nombre'])->toBe('foto-1.png')
        ->and($evidencias[1]['es_imagen'])->toBeFalse()
        ->and($evidencias[1]['nombre'])->toBe('manual.pdf');
});
