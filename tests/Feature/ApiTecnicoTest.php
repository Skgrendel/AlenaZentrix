<?php

use App\Enums\EstadoOrden;
use App\Enums\TipoServicio;
use App\Models\OrdenJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── Helpers ──────────────────────────────────────────────────────────────────

function tecnicoConToken(): array
{
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    return [$user, $token];
}

function ordenAsignada(User $user, array $overrides = []): OrdenJob
{
    return OrdenJob::factory()->create(array_merge([
        'company_id' => $user->company_id,
        'personals_id' => $user->personals_id,
        'tipo_servicio' => TipoServicio::Investigacion->value,
        'estado' => EstadoOrden::Asignada->value,
    ], $overrides));
}

// ── Login ─────────────────────────────────────────────────────────────────────

test('POST /api/auth/login devuelve token con credenciales válidas', function () {
    $user = User::factory()->create(['password' => bcrypt('password')]);

    $response = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['token', 'user' => ['id', 'email', 'nombre', 'personals_id']]);
});

test('POST /api/auth/login rechaza credenciales incorrectas', function () {
    $user = User::factory()->create(['password' => bcrypt('password')]);

    $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'wrong',
    ])->assertStatus(401);
});

test('POST /api/auth/login rechaza usuario desactivado', function () {
    $user = User::factory()->create(['password' => bcrypt('password'), 'estado' => false]);

    $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertStatus(403);
});

test('DELETE /api/auth/logout revoca el token', function () {
    [$user, $token] = tecnicoConToken();

    expect($user->tokens()->count())->toBe(1);

    $this->withToken($token)->deleteJson('/api/auth/logout')->assertOk();

    expect($user->tokens()->count())->toBe(0);
});

// ── Mis órdenes ───────────────────────────────────────────────────────────────

test('GET /api/tecnico/mis-ordenes requiere autenticación', function () {
    $this->getJson('/api/tecnico/mis-ordenes')->assertStatus(401);
});

test('GET /api/tecnico/mis-ordenes devuelve solo las órdenes del técnico', function () {
    [$user, $token] = tecnicoConToken();

    $mia = ordenAsignada($user);

    // Orden de otro técnico (misma empresa)
    $otro = User::factory()->create(['company_id' => $user->company_id]);
    ordenAsignada($otro);

    $response = $this->withToken($token)->getJson('/api/tecnico/mis-ordenes');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toContain($mia->id);
    expect(count($ids))->toBe(1);
});

test('GET /api/tecnico/mis-ordenes incluye acciones disponibles', function () {
    [$user, $token] = tecnicoConToken();
    ordenAsignada($user);

    $response = $this->withToken($token)->getJson('/api/tecnico/mis-ordenes');

    $response->assertOk()
        ->assertJsonStructure(['data' => [['acciones' => ['puede_iniciar', 'puede_reportar', 'puede_editar_reporte']]]]);
});

// ── Ver orden ─────────────────────────────────────────────────────────────────

test('GET /api/tecnico/ordenes/{orden} devuelve el detalle de la orden', function () {
    [$user, $token] = tecnicoConToken();
    $orden = ordenAsignada($user);

    $this->withToken($token)
        ->getJson("/api/tecnico/ordenes/{$orden->id}")
        ->assertOk()
        ->assertJsonPath('data.numero_orden', $orden->numero_orden);
});

// ── Iniciar ───────────────────────────────────────────────────────────────────

test('POST /api/tecnico/ordenes/{orden}/iniciar cambia estado a EnProgreso', function () {
    [$user, $token] = tecnicoConToken();
    $orden = ordenAsignada($user);

    $this->withToken($token)
        ->postJson("/api/tecnico/ordenes/{$orden->id}/iniciar")
        ->assertOk()
        ->assertJsonPath('orden.estado.value', EstadoOrden::EnProgreso->value);

    expect($orden->fresh()->estado)->toBe(EstadoOrden::EnProgreso);
});

test('POST /api/tecnico/ordenes/{orden}/iniciar falla si ya está en progreso', function () {
    [$user, $token] = tecnicoConToken();
    $orden = ordenAsignada($user, ['estado' => EstadoOrden::EnProgreso->value]);

    $this->withToken($token)
        ->postJson("/api/tecnico/ordenes/{$orden->id}/iniciar")
        ->assertStatus(422);
});

// ── Cerrar vigilancia ─────────────────────────────────────────────────────────

test('POST cerrar-vigilancia cambia estado a EnRevision', function () {
    [$user, $token] = tecnicoConToken();
    $orden = ordenAsignada($user, [
        'tipo_servicio' => TipoServicio::Vigilancia->value,
        'estado' => EstadoOrden::EnProgreso->value,
    ]);

    $this->withToken($token)
        ->postJson("/api/tecnico/ordenes/{$orden->id}/cerrar-vigilancia")
        ->assertOk()
        ->assertJsonPath('orden.estado.value', EstadoOrden::EnRevision->value);
});

test('POST cerrar-vigilancia falla si no es Vigilancia', function () {
    [$user, $token] = tecnicoConToken();
    $orden = ordenAsignada($user, ['estado' => EstadoOrden::EnProgreso->value]);

    $this->withToken($token)
        ->postJson("/api/tecnico/ordenes/{$orden->id}/cerrar-vigilancia")
        ->assertStatus(422);
});
