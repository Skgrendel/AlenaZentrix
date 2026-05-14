<?php

use App\Enums\TipoServicio;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('AuditoriaCampo enum case exists with correct label and color', function () {
    $case = TipoServicio::AuditoriaCampo;
    expect($case->label())->toBe('Auditorías de Campo')
        ->and($case->color())->toBe('purple')
        ->and($case->tieneTecnicoAsignado())->toBeTrue();
});

it('can create an OrdenJob with tipo_auditoria_campo', function () {
    $company = \App\Models\Company::factory()->create();
    $user = \App\Models\User::factory()->create(['company_id' => $company->id, 'is_superadmin' => false]);
    $this->actingAs($user);

    $orden = \App\Models\OrdenJob::create([
        'tipo_servicio' => \App\Enums\TipoServicio::AuditoriaCampo,
        'tipo_auditoria_campo' => 'visita_calidad_emergencia',
        'prioridad' => \App\Enums\PrioridadOrden::Media,
        'estado' => \App\Enums\EstadoOrden::Pendiente,
        'estado_servicio' => true,
    ]);

    expect($orden->tipo_auditoria_campo)->toBe('visita_calidad_emergencia')
        ->and($orden->tipo_servicio)->toBe(\App\Enums\TipoServicio::AuditoriaCampo);
});
