<?php

use App\Enums\EstadoOrden;
use App\Enums\TipoServicio;
use App\Models\Company;
use App\Models\Convencion;
use App\Models\OrdenJob;
use App\Models\reportes;
use App\Models\TipoComercio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

test('reglaPathDeLaEmpresa rechaza traversal, barras dobles y backslashes, pero acepta rutas previas al editar', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $orden = OrdenJob::factory()->create([
        'company_id' => $user->company_id,
        'personals_id' => $user->personals_id,
        'tipo_servicio' => TipoServicio::Gestion_Riesgo->value,
        'estado' => EstadoOrden::EnProgreso->value,
    ]);

    foreach ([
        "{$user->company_id}/../secreto.jpg",
        "{$user->company_id}//foto.jpg",
        "{$user->company_id}\\foto.jpg",
        "{$user->company_id}/./foto.jpg",
    ] as $rutaInvalida) {
        $this->withToken($token)
            ->postJson("/api/tecnico/ordenes/{$orden->id}/reporte", [
                'observaciones' => 'Prueba',
                'imagenes' => [$rutaInvalida],
            ])
            ->assertStatus(422);
    }

    // Al editar un reporte que tenía fotos guardadas desde la web (sin prefijo company_id), las conserva
    $orden->update(['estado' => EstadoOrden::Rechazada->value, 'sincronizado' => false]);
    reportes::create([
        'orden_job_id' => $orden->id,
        'personals_id' => $user->personals_id,
        'company_id' => $user->company_id,
        'numero_orden' => $orden->numero_orden,
        'imagenes' => ["reportes/{$orden->numero_orden}/foto-web.jpg"],
    ]);

    $this->withToken($token)
        ->putJson("/api/tecnico/ordenes/{$orden->id}/reporte", [
            'observaciones' => 'Reenvío conservando foto web',
            'imagenes' => ["reportes/{$orden->numero_orden}/foto-web.jpg"],
        ])
        ->assertOk();
});

test('escritura de reportes exige ser dueno o tener ordenes.editar (ordenes.ver no basta)', function () {
    $permVer = Permission::firstOrCreate(['name' => 'ordenes.ver', 'guard_name' => 'web']);

    $dueno = User::factory()->create();
    $observador = User::factory()->create(['company_id' => $dueno->company_id]);
    $observador->givePermissionTo($permVer);

    $orden = OrdenJob::factory()->create([
        'company_id' => $dueno->company_id,
        'personals_id' => $dueno->personals_id,
        'tipo_servicio' => TipoServicio::Gestion_Riesgo->value,
        'estado' => EstadoOrden::EnProgreso->value,
    ]);

    $tokenObservador = $observador->createToken('test')->plainTextToken;

    $this->withToken($tokenObservador)
        ->postJson("/api/tecnico/ordenes/{$orden->id}/reporte", [
            'observaciones' => 'Intento de escritura sin ser dueño',
        ])
        ->assertForbidden();
});

test('vigilancia: store es idempotente por fecha_turno incluso si la orden ya paso a EnRevision y permite turnos en EnRevision', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $orden = OrdenJob::factory()->create([
        'company_id' => $user->company_id,
        'personals_id' => $user->personals_id,
        'tipo_servicio' => TipoServicio::Vigilancia->value,
        'estado' => EstadoOrden::EnProgreso->value,
    ]);

    $payload = [
        'fecha_turno' => '2026-10-15',
        'hora_inicio' => '08:00',
        'hora_fin' => '16:00',
        'observaciones' => 'Turno 1',
    ];

    $this->withToken($token)
        ->postJson("/api/tecnico/ordenes/{$orden->id}/reporte", $payload)
        ->assertCreated();

    $orden->update(['estado' => EstadoOrden::EnRevision->value]);

    // Reintento del mismo fecha_turno cuando la orden ya está EnRevision devuelve 200 idempotente
    $this->withToken($token)
        ->postJson("/api/tecnico/ordenes/{$orden->id}/reporte", $payload)
        ->assertOk();

    expect($orden->reportes()->count())->toBe(1);

    // Nuevo turno en fecha distinta estando EnRevision se permite
    $this->withToken($token)
        ->postJson("/api/tecnico/ordenes/{$orden->id}/reporte", [
            'fecha_turno' => '2026-10-16',
            'hora_inicio' => '08:00',
            'hora_fin' => '16:00',
            'observaciones' => 'Turno 2',
        ])
        ->assertCreated();

    expect($orden->reportes()->count())->toBe(2);
});

test('fraudes: al editar con hay_fuga_gas=true la foto_detector_fuga es opcional y limpia requiere_correccion', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $orden = OrdenJob::factory()->create([
        'company_id' => $user->company_id,
        'personals_id' => $user->personals_id,
        'tipo_servicio' => TipoServicio::Fraudes->value,
        'estado' => EstadoOrden::Rechazada->value,
        'sincronizado' => false,
    ]);

    $reporte = reportes::create([
        'orden_job_id' => $orden->id,
        'personals_id' => $user->personals_id,
        'company_id' => $user->company_id,
        'numero_orden' => $orden->numero_orden,
        'requiere_correccion' => true,
        'imagenes' => ["{$user->company_id}/reportes/detector-previo.jpg"],
    ]);

    $tipoComercio = TipoComercio::create([
        'company_id' => $user->company_id,
        'nombre' => 'Panadería',
        'activo' => true,
    ]);

    $this->withToken($token)
        ->putJson("/api/tecnico/ordenes/{$orden->id}/reporte", [
            'hay_fuga_gas' => true,
            'nombre_comercio' => 'Panadería Central',
            'tipo_comercio_id' => $tipoComercio->id,
            'imagenes' => ["{$user->company_id}/reportes/detector-previo.jpg"],
            'observaciones' => 'Corrección sin subir nueva foto de detector',
        ])
        ->assertOk();

    expect($reporte->fresh()->requiere_correccion)->toBeFalse()
        ->and($orden->fresh()->estado)->toBe(EstadoOrden::EnRevision);
});

test('catalogos filtran por company_id, firmas validan formato base64 y actividades sin clave se normalizan a string vacio', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $otraEmpresa = Company::factory()->create();
    $convencionAjena = Convencion::create([
        'company_id' => $otraEmpresa->id,
        'nombre' => 'Convención Ajena',
        'activo' => true,
    ]);

    $ordenInv = OrdenJob::factory()->create([
        'company_id' => $user->company_id,
        'personals_id' => $user->personals_id,
        'tipo_servicio' => TipoServicio::Investigacion->value,
        'estado' => EstadoOrden::EnProgreso->value,
    ]);

    // Catálogo de otra empresa rechazado
    $this->withToken($token)
        ->postJson("/api/tecnico/ordenes/{$ordenInv->id}/reporte", [
            'convencion_id' => $convencionAjena->id,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['convencion_id']);

    // Actividades sin 'actividades' se normalizan a ''
    $convencionPropia = Convencion::create([
        'company_id' => $user->company_id,
        'nombre' => 'Convención Propia',
        'activo' => true,
    ]);

    $this->withToken($token)
        ->postJson("/api/tecnico/ordenes/{$ordenInv->id}/reporte", [
            'convencion_id' => $convencionPropia->id,
            'tipo_investigado' => 'persona',
            'nombre_investigado' => 'Juan Pérez',
            'identificacion' => '123456789',
            'actividades' => [
                ['descripcion_orden' => 'Verificar predio'],
            ],
        ])
        ->assertCreated();

    $repInv = $ordenInv->reportes()->first();
    expect($repInv->actividades[0]['actividades'])->toBe('');

    // Firma inválida en Auditoría de Campo es rechazada con 422
    $ordenCampo = OrdenJob::factory()->create([
        'company_id' => $user->company_id,
        'personals_id' => $user->personals_id,
        'tipo_servicio' => TipoServicio::AuditoriaCampo->value,
        'tipo_auditoria_campo' => 'visita_calidad_emergencia',
        'estado' => EstadoOrden::EnProgreso->value,
    ]);

    $this->withToken($token)
        ->postJson("/api/tecnico/ordenes/{$ordenCampo->id}/reporte", [
            'autoriza' => true,
            'firma_usuario' => 'no-es-un-data-uri-valido',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['firma_usuario']);
});
