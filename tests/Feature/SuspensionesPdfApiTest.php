<?php

use App\Enums\EstadoOrden;
use App\Enums\TipoServicio;
use App\Http\Controllers\ReportePdfController;
use App\Models\OrdenJob;
use App\Models\reportes;
use App\Models\TipoAnomalia;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withHeader('X-App-Version', '1.1.0');
});

function ordenSuspensiones(User $user): OrdenJob
{
    return OrdenJob::factory()->create([
        'company_id' => $user->company_id,
        'personals_id' => $user->personals_id,
        'tipo_servicio' => TipoServicio::AuditoriaCampo->value,
        'tipo_auditoria_campo' => 'visita_calidad_suspensiones',
        'estado' => EstadoOrden::EnProgreso->value,
    ]);
}

/** @return array<string, mixed> */
function payloadSuspensiones(User $user, array $overrides = []): array
{
    $fotos = fn (string $prefijo): array => collect(range(1, 3))
        ->map(fn (int $i): string => "{$user->company_id}/{$prefijo}-{$i}.jpg")
        ->all();

    return array_merge([
        'categoria_campo' => 'residencial',
        'suspension_ejecutada' => true,
        'metodo_suspension' => 'sonda_ips',
        'serie_sonda' => '123456',
        'serie_caps' => '654321',
        'fotos_fachada' => $fotos('fachada'),
        'fotos_suspension' => $fotos('suspension'),
        'lectura_medidor' => '1234.5',
        'condicion_sellos' => 'Intactos',
        'elementos_rotos' => 'Ninguno',
        'fotos_medidor' => $fotos('medidor'),
        'observaciones' => 'Sin novedad',
    ], $overrides);
}

test('API suspensiones guarda los campos en datos_servicio sin exigir campos comunes', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;
    $orden = ordenSuspensiones($user);

    $this->withToken($token)
        ->postJson("/api/tecnico/ordenes/{$orden->id}/reporte", payloadSuspensiones($user))
        ->assertCreated();

    $reporte = $orden->fresh()->reportes()->first();

    expect($reporte->datos_servicio['metodo_suspension'])->toBe('sonda_ips')
        ->and($reporte->datos_servicio['serie_sonda'])->toBe('123456')
        ->and($reporte->datos_servicio['fotos_fachada'])->toHaveCount(3)
        ->and($reporte->datos_servicio['fotos_suspension'])->toHaveCount(3)
        ->and($reporte->observaciones)->toBe('Sin novedad');
});

test('API suspensiones no ejecutada exige motivo y acepta sin metodo ni series', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;
    $orden = ordenSuspensiones($user);

    $base = payloadSuspensiones($user, [
        'suspension_ejecutada' => false,
        'metodo_suspension' => null,
        'serie_sonda' => null,
        'serie_caps' => null,
        'fotos_suspension' => null,
        'lectura_medidor' => null,
        'condicion_sellos' => null,
        'elementos_rotos' => null,
        'fotos_medidor' => null,
    ]);

    $this->withToken($token)
        ->postJson("/api/tecnico/ordenes/{$orden->id}/reporte", $base)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['motivo_no_ejecucion', 'justificacion_no_ejecucion', 'fotos_no_ejecucion']);

    $this->withToken($token)
        ->postJson("/api/tecnico/ordenes/{$orden->id}/reporte", [
            ...$base,
            'motivo_no_ejecucion' => 'predio_cerrado',
            'justificacion_no_ejecucion' => 'Predio cerrado',
            'fotos_no_ejecucion' => ["{$user->company_id}/no-ejecucion.jpg"],
        ])
        ->assertCreated();
});

test('API suspensiones con sonda exige series', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;
    $orden = ordenSuspensiones($user);

    $this->withToken($token)
        ->postJson("/api/tecnico/ordenes/{$orden->id}/reporte", payloadSuspensiones($user, ['serie_sonda' => null, 'serie_caps' => 'abc']))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['serie_sonda', 'serie_caps']);
});

test('API suspensiones ejecutada exige datos y fotos del medidor', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;
    $orden = ordenSuspensiones($user);

    $this->withToken($token)
        ->postJson("/api/tecnico/ordenes/{$orden->id}/reporte", payloadSuspensiones($user, [
            'lectura_medidor' => 'abc',
            'fotos_medidor' => [],
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['lectura_medidor', 'fotos_medidor']);
});

test('API suspensiones rechaza mas de 10 fotos y rutas de otra empresa', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;
    $orden = ordenSuspensiones($user);

    $this->withToken($token)
        ->postJson("/api/tecnico/ordenes/{$orden->id}/reporte", payloadSuspensiones($user, [
            'fotos_medidor' => array_fill(0, 11, "{$user->company_id}/m.jpg"),
            'fotos_fachada' => ['otra/a.jpg', 'otra/b.jpg', 'otra/c.jpg'],
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['fotos_medidor', 'fotos_fachada.0']);
});

test('API suspensiones exige al menos 3 fotos por galeria', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;
    $orden = ordenSuspensiones($user);

    $payload = payloadSuspensiones($user);
    $payload['fotos_fachada'] = array_slice($payload['fotos_fachada'], 0, 2);
    $payload['fotos_suspension'] = array_slice($payload['fotos_suspension'], 0, 2);

    $this->withToken($token)
        ->postJson("/api/tecnico/ordenes/{$orden->id}/reporte", $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['fotos_fachada', 'fotos_suspension']);
});

test('coordinador descarga el pdf de auditoria campo suspensiones', function () {
    Storage::fake('s3');
    $this->seed(PermissionsSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('Coordinador');

    $orden = OrdenJob::factory()->create([
        'tipo_servicio' => TipoServicio::AuditoriaCampo->value,
        'tipo_auditoria_campo' => 'visita_calidad_suspensiones',
        'estado' => EstadoOrden::EnRevision->value,
        'creado_por_id' => $user->id,
        'company_id' => $user->company_id,
    ]);

    reportes::create([
        'orden_job_id' => $orden->id,
        'numero_orden' => $orden->numero_orden,
        'company_id' => $user->company_id,
        'imagenes' => [],
        'observaciones' => 'Todo bien',
        'datos_servicio' => [
            'categoria_campo' => 'comercial',
            'suspension_ejecutada' => true,
            'metodo_suspension' => 'sonda_cts',
            'serie_sonda' => '111',
            'serie_caps' => '222',
            'fotos_fachada' => [],
            'fotos_suspension' => [],
            'lectura_medidor' => '10.5',
            'condicion_sellos' => 'Intactos',
            'elementos_rotos' => 'Ninguno',
            'fotos_medidor' => [],
        ],
    ]);

    $this->actingAs($user)
        ->get(route('operaciones.ordenes-trabajo.reporte-auditoria-campo', $orden))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    reportes::query()->delete();

    reportes::create([
        'orden_job_id' => $orden->id,
        'numero_orden' => $orden->numero_orden,
        'company_id' => $user->company_id,
        'imagenes' => [],
        'datos_servicio' => [
            'categoria_campo' => 'comercial',
            'suspension_ejecutada' => false,
            'motivo_no_ejecucion' => 'predio_cerrado',
            'justificacion_no_ejecucion' => 'No había nadie',
            'fotos_fachada' => [],
            'fotos_no_ejecucion' => [],
        ],
    ]);

    $this->actingAs($user)
        ->get(route('operaciones.ordenes-trabajo.reporte-auditoria-campo', $orden))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

dataset('lecturasReferencia', [
    'coincide' => ['100.50', '100.5', true],
    'no coincide' => ['100.50', '99', false],
    'sin referencia' => [null, '99', null],
]);

test('pdf suspensiones calcula si la lectura registrada coincide con la referencia', function (?string $referencia, string $registrada, ?bool $esperado) {
    Storage::fake('s3');
    $this->seed(PermissionsSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('Coordinador');

    $orden = OrdenJob::factory()->create([
        'tipo_servicio' => TipoServicio::AuditoriaCampo->value,
        'tipo_auditoria_campo' => 'visita_calidad_suspensiones',
        'estado' => EstadoOrden::EnRevision->value,
        'creado_por_id' => $user->id,
        'company_id' => $user->company_id,
        'lectura_referencia' => $referencia,
    ]);

    $reporte = reportes::create([
        'orden_job_id' => $orden->id,
        'numero_orden' => $orden->numero_orden,
        'company_id' => $user->company_id,
        'imagenes' => [],
        'datos_servicio' => [
            'categoria_campo' => 'comercial',
            'suspension_ejecutada' => true,
            'metodo_suspension' => 'taponamiento_acometida',
            'lectura_medidor' => $registrada,
            'condicion_sellos' => 'Intactos',
            'elementos_rotos' => 'Ninguno',
        ],
    ]);

    $metodo = new ReflectionMethod(ReportePdfController::class, 'buildDatosReporteAuditoriaCampo');
    $datos = $metodo->invoke(app(ReportePdfController::class), $reporte->load('ordenJob'));

    expect($datos['lectura_coincide'])->toBe($esperado);

    $this->actingAs($user)
        ->get(route('operaciones.ordenes-trabajo.reporte-auditoria-campo', $orden))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
})->with('lecturasReferencia');

test('API expone lectura_referencia en la orden del tecnico cuando existe', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;
    $orden = ordenSuspensiones($user);
    $orden->update(['lectura_referencia' => '100.5']);

    $this->withToken($token)
        ->getJson("/api/tecnico/ordenes/{$orden->id}")
        ->assertOk()
        ->assertJsonPath('data.lectura_referencia', '100.5');
});

test('API suspensiones ejecutada sin anomalia, sellos ni elementos rotos es valida', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;
    $orden = ordenSuspensiones($user);

    $this->withToken($token)
        ->postJson("/api/tecnico/ordenes/{$orden->id}/reporte", payloadSuspensiones($user, [
            'condicion_sellos' => null,
            'elementos_rotos' => null,
        ]))
        ->assertCreated();
});

test('API suspensiones no ejecutada guarda anomalias, sellos y elementos rotos', function () {
    $user = User::factory()->create();
    $anomalia = TipoAnomalia::create(['nombre' => 'Vidrio roto', 'activo' => true, 'company_id' => $user->company_id]);
    $token = $user->createToken('test')->plainTextToken;
    $orden = ordenSuspensiones($user);

    $this->withToken($token)
        ->postJson("/api/tecnico/ordenes/{$orden->id}/reporte", payloadSuspensiones($user, [
            'suspension_ejecutada' => false,
            'metodo_suspension' => null,
            'serie_sonda' => null,
            'serie_caps' => null,
            'fotos_suspension' => null,
            'lectura_medidor' => null,
            'fotos_medidor' => null,
            'motivo_no_ejecucion' => 'predio_cerrado',
            'justificacion_no_ejecucion' => 'Predio cerrado',
            'fotos_no_ejecucion' => ["{$user->company_id}/no-ejecucion.jpg"],
            'anomalias_medidor' => [$anomalia->id],
            'condicion_sellos' => 'Violentados',
            'elementos_rotos' => 'Visor',
        ]))
        ->assertCreated();

    $datos = $orden->fresh()->reportes()->first()->datos_servicio;

    expect($datos['anomalias_medidor'])->toBe([$anomalia->id])
        ->and($datos['condicion_sellos'])->toBe('Violentados')
        ->and($datos['elementos_rotos'])->toBe('Visor');
});

test('pdf suspensiones no ejecutada muestra observaciones del medidor', function () {
    Storage::fake('s3');
    $this->seed(PermissionsSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('Coordinador');
    $anomalia = TipoAnomalia::create(['nombre' => 'Vidrio roto', 'activo' => true, 'company_id' => $user->company_id]);

    $orden = OrdenJob::factory()->create([
        'tipo_servicio' => TipoServicio::AuditoriaCampo->value,
        'tipo_auditoria_campo' => 'visita_calidad_suspensiones',
        'estado' => EstadoOrden::EnRevision->value,
        'creado_por_id' => $user->id,
        'company_id' => $user->company_id,
    ]);

    $reporte = reportes::create([
        'orden_job_id' => $orden->id,
        'numero_orden' => $orden->numero_orden,
        'company_id' => $user->company_id,
        'imagenes' => [],
        'datos_servicio' => [
            'categoria_campo' => 'comercial',
            'suspension_ejecutada' => false,
            'motivo_no_ejecucion' => 'predio_cerrado',
            'justificacion_no_ejecucion' => 'Nadie',
            'anomalias_medidor' => [$anomalia->id],
            'condicion_sellos' => 'Violentados',
            'elementos_rotos' => 'Visor',
        ],
    ]);

    $metodo = new ReflectionMethod(ReportePdfController::class, 'buildDatosReporteAuditoriaCampo');
    $datos = $metodo->invoke(app(ReportePdfController::class), $reporte->load('ordenJob'));

    expect($datos['anomalias_medidor'])->toBe('Vidrio roto')
        ->and($datos['condicion_sellos'])->toBe('Violentados')
        ->and($datos['elementos_rotos'])->toBe('Visor')
        ->and($datos['lectura_coincide'])->toBeNull();

    $this->actingAs($user)
        ->get(route('operaciones.ordenes-trabajo.reporte-auditoria-campo', $orden))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

test('API suspensiones rechaza ids de anomalias inexistentes', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;
    $orden = ordenSuspensiones($user);

    $this->withToken($token)
        ->postJson("/api/tecnico/ordenes/{$orden->id}/reporte", payloadSuspensiones($user, [
            'anomalias_medidor' => ['00000000-0000-0000-0000-000000000000'],
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['anomalias_medidor.0']);
});

test('API expone subtipo, tecnico y comentario tecnico de la orden de suspensiones', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;
    $orden = ordenSuspensiones($user);
    $orden->update(['tecnico' => 'Carlos Ruiz', 'comentario_tecnico' => 'Medidor en patio']);

    $this->withToken($token)
        ->getJson("/api/tecnico/ordenes/{$orden->id}")
        ->assertOk()
        ->assertJsonPath('data.tipo_auditoria_campo', 'visita_calidad_suspensiones')
        ->assertJsonPath('data.tipo_auditoria_campo_label', 'Visita de Calidad — Suspensiones')
        ->assertJsonPath('data.tecnico', 'Carlos Ruiz')
        ->assertJsonPath('data.comentario_tecnico', 'Medidor en patio');
});
