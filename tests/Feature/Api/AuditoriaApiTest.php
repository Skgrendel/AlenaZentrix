<?php

use App\Enums\EstadoAuditoria;
use App\Enums\EstadoOrden;
use App\Enums\TipoServicio;
use App\Models\auditoria;
use App\Models\OrdenJob;
use App\Models\reportes;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── Helpers ──────────────────────────────────────────────────────────────────

function auditoriaConToken(): array
{
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    return [$user, $token];
}

function auditoriaDeOrdenDelTecnico(User $tecnico): auditoria
{
    $orden = OrdenJob::factory()->create([
        'company_id' => $tecnico->company_id,
        'personals_id' => $tecnico->personals_id,
        'tipo_servicio' => TipoServicio::Fraudes->value,
        'estado' => EstadoOrden::Completada->value,
    ]);

    $reporte = reportes::factory()->create([
        'orden_job_id' => $orden->id,
        'personals_id' => $tecnico->personals_id,
        'numero_orden' => $orden->numero_orden,
        'company_id' => $tecnico->company_id,
    ]);

    return auditoria::create([
        'reportes_id' => $reporte->id,
        'estado' => EstadoAuditoria::PendienteAuditoria->value,
    ]);
}

// ── GET /api/auditorias/{auditoria} ─────────────────────────────────────────

test('GET /api/auditorias/{auditoria} requiere autenticación', function () {
    [$user] = auditoriaConToken();
    $auditoriaRecord = auditoriaDeOrdenDelTecnico($user);

    $this->getJson("/api/auditorias/{$auditoriaRecord->id}")->assertStatus(401);
});

test('el técnico dueño de la orden puede ver su propia auditoría sin permiso auditorias.ver', function () {
    [$user, $token] = auditoriaConToken();
    $auditoriaRecord = auditoriaDeOrdenDelTecnico($user);

    $this->withToken($token)
        ->getJson("/api/auditorias/{$auditoriaRecord->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $auditoriaRecord->id);
});

test('un técnico que no es dueño de la orden y sin permiso recibe 403', function () {
    [$user, $token] = auditoriaConToken();
    $otroTecnico = User::factory()->create();
    $auditoriaRecord = auditoriaDeOrdenDelTecnico($otroTecnico);

    $this->withToken($token)
        ->getJson("/api/auditorias/{$auditoriaRecord->id}")
        ->assertStatus(403);
});

test('un usuario con rol Auditor puede ver cualquier auditoría', function () {
    $this->seed(PermissionsSeeder::class);

    $auditor = User::factory()->create();
    $auditor->assignRole('Auditor');
    $auditorToken = $auditor->createToken('test')->plainTextToken;

    $tecnico = User::factory()->create();
    $auditoriaRecord = auditoriaDeOrdenDelTecnico($tecnico);

    $this->withToken($auditorToken)
        ->getJson("/api/auditorias/{$auditoriaRecord->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $auditoriaRecord->id);
});

// ── PUT /api/auditorias/{auditoria} ─────────────────────────────────────────

test('PUT /api/auditorias/{auditoria} guarda evidencias y resultado', function () {
    [$user, $token] = auditoriaConToken();
    $auditoriaRecord = auditoriaDeOrdenDelTecnico($user);

    $payload = [
        'evidencias' => ["{$user->company_id}/auditorias/evidencia1.jpg"],
        'resultado' => EstadoAuditoria::Confirmado->value,
        'observaciones' => 'Hallazgo confirmado en sitio.',
    ];

    $this->withToken($token)
        ->putJson("/api/auditorias/{$auditoriaRecord->id}", $payload)
        ->assertOk()
        ->assertJsonPath('auditoria.estado.value', EstadoAuditoria::Confirmado->value);

    $auditoriaRecord->refresh();
    expect($auditoriaRecord->estado)->toBe(EstadoAuditoria::Confirmado);
    expect($auditoriaRecord->evidencias)->toBe(["{$user->company_id}/auditorias/evidencia1.jpg"]);
    expect($auditoriaRecord->auditado_por_id)->toBe($user->id);
    expect($auditoriaRecord->fecha_auditoria)->not->toBeNull();
});

test('PUT actualiza el contrato de la orden cuando se envía', function () {
    [$user, $token] = auditoriaConToken();
    $auditoriaRecord = auditoriaDeOrdenDelTecnico($user);

    $payload = [
        'evidencias' => ["{$user->company_id}/auditorias/evidencia1.jpg"],
        'resultado' => EstadoAuditoria::Negativo->value,
        'contrato' => '99887766',
    ];

    $this->withToken($token)
        ->putJson("/api/auditorias/{$auditoriaRecord->id}", $payload)
        ->assertOk();

    expect($auditoriaRecord->reporte->ordenJob->fresh()->contrato)->toBe('99887766');
});

test('PUT valida que resultado sea confirmado o negativo', function () {
    [$user, $token] = auditoriaConToken();
    $auditoriaRecord = auditoriaDeOrdenDelTecnico($user);

    $payload = [
        'evidencias' => ['auditorias/evidencia1.jpg'],
        'resultado' => EstadoAuditoria::PendienteAuditoria->value,
    ];

    $this->withToken($token)
        ->putJson("/api/auditorias/{$auditoriaRecord->id}", $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors('resultado');
});

test('PUT devuelve 403 para un usuario no autorizado', function () {
    [$user, $token] = auditoriaConToken();
    $otroTecnico = User::factory()->create();
    $auditoriaRecord = auditoriaDeOrdenDelTecnico($otroTecnico);

    $payload = [
        'evidencias' => ['auditorias/evidencia1.jpg'],
        'resultado' => EstadoAuditoria::Confirmado->value,
    ];

    $this->withToken($token)
        ->putJson("/api/auditorias/{$auditoriaRecord->id}", $payload)
        ->assertStatus(403);
});
