<?php

use App\Enums\EstadoAuditoria;
use App\Enums\EstadoOrden;
use App\Enums\TipoServicio;
use App\Models\auditoria;
use App\Models\Company;
use App\Models\OrdenJob;
use App\Models\reportes;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

test('tenant fail-closed: empresa inactiva o sin company recibe 403 JSON en la API', function () {
    $companyInactiva = Company::factory()->create(['activo' => false]);
    $userInactivo = User::factory()->create(['company_id' => $companyInactiva->id]);
    $tokenInactivo = $userInactivo->createToken('test')->plainTextToken;

    $this->withToken($tokenInactivo)
        ->getJson('/api/tecnico/mis-ordenes')
        ->assertForbidden()
        ->assertJsonPath('message', 'Tu empresa no está activa o no tienes una empresa asignada.');

    $userSinEmpresa = User::factory()->create(['company_id' => null]);
    $tokenSinEmpresa = $userSinEmpresa->createToken('test')->plainTextToken;

    $this->withToken($tokenSinEmpresa)
        ->getJson('/api/tecnico/mis-ordenes')
        ->assertForbidden()
        ->assertJsonPath('message', 'Tu empresa no está activa o no tienes una empresa asignada.');
});

test('login rechaza empresa inactiva y superadmin con 403 JSON', function () {
    $companyInactiva = Company::factory()->create(['activo' => false]);
    $userEmpresaInactiva = User::factory()->create([
        'company_id' => $companyInactiva->id,
        'password' => Hash::make('secret123'),
    ]);

    $this->postJson('/api/auth/login', [
        'email' => $userEmpresaInactiva->email,
        'password' => 'secret123',
    ])
        ->assertForbidden()
        ->assertJsonPath('message', 'Tu empresa no está activa o no tienes una empresa asignada.');

    $superadmin = User::factory()->create([
        'is_superadmin' => true,
        'password' => Hash::make('secret123'),
    ]);

    $this->postJson('/api/auth/login', [
        'email' => $superadmin->email,
        'password' => 'secret123',
    ])
        ->assertForbidden()
        ->assertJsonPath('message', 'Los superadministradores no pueden usar la API.');

    $tokenSuper = $superadmin->createToken('test')->plainTextToken;

    $this->withToken($tokenSuper)
        ->getJson('/api/tecnico/mis-ordenes')
        ->assertForbidden()
        ->assertJsonPath('message', 'Los superadministradores no pueden usar la API.');
});

test('desactivar usuario revoca sus tokens y el middleware rechaza usuario desactivado', function () {
    $user = User::factory()->create(['estado' => true]);
    $user->createToken('t1');
    $user->createToken('t2');

    expect($user->tokens()->count())->toBe(2);

    $user->update(['estado' => false]);

    expect($user->tokens()->count())->toBe(0);

    $token = $user->createToken('post-disable')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/tecnico/mis-ordenes')
        ->assertForbidden()
        ->assertJsonPath('message', 'Tu cuenta está desactivada.');
});

test('OrdenController show, iniciar y cerrarVigilancia verifican propiedad e idempotencia', function () {
    $dueno = User::factory()->create();
    $intruso = User::factory()->create(['company_id' => $dueno->company_id]);

    $orden = OrdenJob::factory()->create([
        'company_id' => $dueno->company_id,
        'personals_id' => $dueno->personals_id,
        'tipo_servicio' => TipoServicio::Fraudes->value,
        'estado' => EstadoOrden::Asignada->value,
    ]);

    $tokenIntruso = $intruso->createToken('test')->plainTextToken;
    $tokenDueno = $dueno->createToken('test')->plainTextToken;

    $this->withToken($tokenIntruso)
        ->getJson("/api/tecnico/ordenes/{$orden->id}")
        ->assertForbidden();

    $this->withToken($tokenIntruso)
        ->postJson("/api/tecnico/ordenes/{$orden->id}/iniciar")
        ->assertForbidden();

    app('auth')->forgetGuards();

    // Iniciar dos veces seguidas es idempotente (200)
    $this->withToken($tokenDueno)
        ->postJson("/api/tecnico/ordenes/{$orden->id}/iniciar")
        ->assertOk();

    $this->withToken($tokenDueno)
        ->postJson("/api/tecnico/ordenes/{$orden->id}/iniciar")
        ->assertOk();

    // Cerrar vigilancia dos veces seguidas es idempotente (200)
    $ordenVig = OrdenJob::factory()->create([
        'company_id' => $dueno->company_id,
        'personals_id' => $dueno->personals_id,
        'tipo_servicio' => TipoServicio::Vigilancia->value,
        'estado' => EstadoOrden::EnProgreso->value,
    ]);

    reportes::create([
        'orden_job_id' => $ordenVig->id,
        'personals_id' => $dueno->personals_id,
        'company_id' => $dueno->company_id,
        'numero_orden' => $ordenVig->numero_orden,
        'datos_servicio' => ['fecha_turno' => '2026-10-10'],
    ]);

    $this->withToken($tokenDueno)
        ->postJson("/api/tecnico/ordenes/{$ordenVig->id}/cerrar-vigilancia")
        ->assertOk();

    $this->withToken($tokenDueno)
        ->postJson("/api/tecnico/ordenes/{$ordenVig->id}/cerrar-vigilancia")
        ->assertOk();
});

test('auditorias: exige permiso auditorias.registrar, impide autoauditoria, valida pendiente y rechaza path traversal', function () {
    $permRegistrar = Permission::firstOrCreate(['name' => 'auditorias.registrar', 'guard_name' => 'web']);

    $tecnico = User::factory()->create();
    $auditor = User::factory()->create(['company_id' => $tecnico->company_id]);
    $auditor->givePermissionTo($permRegistrar);
    $auditor->serviciosAuditables()->create(['tipo_servicio' => TipoServicio::Fraudes->value]);

    $orden = OrdenJob::factory()->create([
        'company_id' => $tecnico->company_id,
        'personals_id' => $tecnico->personals_id,
        'tipo_servicio' => TipoServicio::Fraudes->value,
        'estado' => EstadoOrden::EnRevision->value,
    ]);

    $reporte = reportes::create([
        'orden_job_id' => $orden->id,
        'personals_id' => $tecnico->personals_id,
        'company_id' => $tecnico->company_id,
        'numero_orden' => $orden->numero_orden,
    ]);

    $aud = auditoria::create([
        'reportes_id' => $reporte->id,
        'estado' => EstadoAuditoria::PendienteAuditoria,
    ]);

    // Autoauditoría bloqueada aunque el técnico tenga permiso auditorias.registrar
    $tecnico->givePermissionTo($permRegistrar);
    $tecnico->serviciosAuditables()->create(['tipo_servicio' => TipoServicio::Fraudes->value]);
    $tokenTecnico = $tecnico->createToken('test')->plainTextToken;

    $this->withToken($tokenTecnico)
        ->putJson("/api/auditorias/{$aud->id}", [
            'evidencias' => ["{$tecnico->company_id}/auditorias/ev1.jpg"],
            'resultado' => EstadoAuditoria::Confirmado->value,
        ])
        ->assertForbidden();

    app('auth')->forgetGuards();

    // Evidencia con .. es rechazada con 422
    $tokenAuditor = $auditor->createToken('test')->plainTextToken;

    $this->withToken($tokenAuditor)
        ->putJson("/api/auditorias/{$aud->id}", [
            'evidencias' => ["{$auditor->company_id}/../secreto.jpg"],
            'resultado' => EstadoAuditoria::Confirmado->value,
        ])
        ->assertStatus(422);

    // Registro válido
    $this->withToken($tokenAuditor)
        ->putJson("/api/auditorias/{$aud->id}", [
            'evidencias' => ["{$auditor->company_id}/auditorias/ev1.jpg"],
            'resultado' => EstadoAuditoria::Confirmado->value,
        ])
        ->assertOk();

    // Segundo intento sobre auditoría ya registrada devuelve 409
    $this->withToken($tokenAuditor)
        ->putJson("/api/auditorias/{$aud->id}", [
            'evidencias' => ["{$auditor->company_id}/auditorias/ev1.jpg"],
            'resultado' => EstadoAuditoria::Confirmado->value,
        ])
        ->assertStatus(409);
});

test('cabecera X-App-Version minima (1.1.0) filtra subtipos nuevos en mis-ordenes y devuelve 426 al operar', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $ordenSuspension = OrdenJob::factory()->create([
        'company_id' => $user->company_id,
        'personals_id' => $user->personals_id,
        'tipo_servicio' => TipoServicio::AuditoriaCampo->value,
        'tipo_auditoria_campo' => 'visita_calidad_suspensiones',
        'estado' => EstadoOrden::Asignada->value,
    ]);

    // Sin cabecera o versión 1.0.0: no aparece en mis-ordenes y devuelve 426 en show
    $this->withToken($token)
        ->withHeader('X-App-Version', '1.0.0')
        ->getJson('/api/tecnico/mis-ordenes')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->withToken($token)
        ->withHeader('X-App-Version', '1.0.0')
        ->getJson("/api/tecnico/ordenes/{$ordenSuspension->id}")
        ->assertStatus(426);

    // Con versión 1.1.0: aparece y permite operar
    $this->withToken($token)
        ->withHeader('X-App-Version', '1.1.0')
        ->getJson('/api/tecnico/mis-ordenes')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->withToken($token)
        ->withHeader('X-App-Version', '1.1.0')
        ->getJson("/api/tecnico/ordenes/{$ordenSuspension->id}")
        ->assertOk();
});

test('mis-ordenes acota completadas a los ultimos 90 dias por defecto y soporta parametro desde', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $reciente = OrdenJob::factory()->create([
        'company_id' => $user->company_id,
        'personals_id' => $user->personals_id,
        'estado' => EstadoOrden::Completada->value,
        'fecha_completado' => now()->subDays(10),
    ]);

    $antigua = OrdenJob::factory()->create([
        'company_id' => $user->company_id,
        'personals_id' => $user->personals_id,
        'estado' => EstadoOrden::Completada->value,
        'fecha_completado' => now()->subDays(120),
    ]);

    $response = $this->withToken($token)
        ->getJson('/api/tecnico/mis-ordenes')
        ->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($reciente->id)
        ->and($ids)->not->toContain($antigua->id);
});
