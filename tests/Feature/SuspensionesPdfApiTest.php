<?php

use App\Enums\EstadoOrden;
use App\Enums\TipoServicio;
use App\Http\Controllers\ReportePdfController;
use App\Models\OrdenJob;
use App\Models\reportes;
use App\Models\TipoAnomalia;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
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

test('API no expone la lectura de referencia al auditor de suspensiones', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;
    $orden = ordenSuspensiones($user);
    $orden->update(['lectura_referencia' => '100.5', 'serie_medidor_referencia' => '981797']);

    $respuesta = $this->withToken($token)
        ->getJson("/api/tecnico/ordenes/{$orden->id}")
        ->assertOk();

    expect($respuesta->json('data'))->not->toHaveKeys(['lectura_referencia', 'serie_medidor_referencia']);
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

test('API suspensiones exige la serie del medidor cuando la orden trae serie de referencia', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;
    $orden = ordenSuspensiones($user);
    $orden->update(['serie_medidor_referencia' => '981797']);

    $this->withToken($token)
        ->postJson("/api/tecnico/ordenes/{$orden->id}/reporte", payloadSuspensiones($user))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('serie_medidor');

    $this->withToken($token)
        ->postJson("/api/tecnico/ordenes/{$orden->id}/reporte", payloadSuspensiones($user, ['serie_medidor' => '981797']))
        ->assertCreated();
});

test('API suspensiones no exige la serie del medidor si la orden no tiene serie de referencia', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;
    $orden = ordenSuspensiones($user);

    $this->withToken($token)
        ->postJson("/api/tecnico/ordenes/{$orden->id}/reporte", payloadSuspensiones($user))
        ->assertCreated();
});

/** Orden con referencia y un reporte del auditor cuya lectura no coincide, para probar la comparación. */
function reporteConComparacion(): array
{
    Storage::fake('s3');
    test()->seed(PermissionsSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('Coordinador');

    $orden = OrdenJob::factory()->create([
        'tipo_servicio' => TipoServicio::AuditoriaCampo->value,
        'tipo_auditoria_campo' => 'visita_calidad_suspensiones',
        'estado' => EstadoOrden::EnRevision->value,
        'creado_por_id' => $user->id,
        'company_id' => $user->company_id,
        'lectura_referencia' => '9999',
        'serie_medidor_referencia' => '981797',
    ]);

    $reporte = reportes::create([
        'orden_job_id' => $orden->id,
        'numero_orden' => $orden->numero_orden,
        'company_id' => $user->company_id,
        'imagenes' => [],
        'datos_servicio' => [
            'categoria_campo' => 'residencial',
            'suspension_ejecutada' => true,
            'metodo_suspension' => 'taponamiento_acometida',
            'serie_medidor' => '981797',
            'lectura_medidor' => '1234',
        ],
    ]);

    return [$user, $orden, $reporte];
}

test('los datos del pdf de suspensiones incluyen la comparación y su resultado', function () {
    [, , $reporte] = reporteConComparacion();

    $metodo = new ReflectionMethod(ReportePdfController::class, 'buildDatosReporteAuditoriaCampo');
    $datos = $metodo->invoke(app(ReportePdfController::class), $reporte->fresh()->load('ordenJob'));

    expect($datos['resultado_comparacion'])->toBe('no_conforme')
        ->and(collect($datos['comparacion']['filas'])->firstWhere('campo', 'lectura')['resultado'])->toBe('no_coincide');
});

test('el pdf de suspensiones muestra la tabla de comparación con el resultado', function () {
    [$user, $orden] = reporteConComparacion();

    $capturado = null;
    $pdf = Mockery::mock(Barryvdh\DomPDF\PDF::class);
    $pdf->shouldReceive('setPaper')->andReturnSelf();
    $pdf->shouldReceive('download')->andReturn(response('pdf'));
    Pdf::shouldReceive('loadView')
        ->once()
        ->withArgs(function (string $vista, array $datos) use (&$capturado): bool {
            $capturado = [$vista, $datos];

            return true;
        })
        ->andReturn($pdf);

    $this->actingAs($user)
        ->get(route('operaciones.ordenes-trabajo.reporte-auditoria-campo', $orden))
        ->assertOk();

    $html = view($capturado[0], $capturado[1])->render();

    expect($html)->toContain('REPORTADO POR EL TÉCNICO VS ENCONTRADO POR EL AUDITOR')
        ->toContain('NO CONFORME')
        ->toContain('Lectura del medidor')
        ->toContain('9999')
        ->toContain('No coincide');
});

test('el pdf de suspensiones sin comparación no muestra esa sección', function () {
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
        'datos_servicio' => ['categoria_campo' => 'comercial', 'suspension_ejecutada' => false, 'motivo_no_ejecucion' => 'predio_cerrado'],
    ]);

    $capturado = null;
    $pdf = Mockery::mock(Barryvdh\DomPDF\PDF::class);
    $pdf->shouldReceive('setPaper')->andReturnSelf();
    $pdf->shouldReceive('download')->andReturn(response('pdf'));
    Pdf::shouldReceive('loadView')->once()->withArgs(function (string $vista, array $datos) use (&$capturado): bool {
        $capturado = [$vista, $datos];

        return true;
    })->andReturn($pdf);

    $this->actingAs($user)->get(route('operaciones.ordenes-trabajo.reporte-auditoria-campo', $orden))->assertOk();

    expect(view($capturado[0], $capturado[1])->render())->not->toContain('REPORTADO POR EL TÉCNICO VS ENCONTRADO');
});
