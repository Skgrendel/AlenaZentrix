<?php

use App\Enums\EstadoOrden;
use App\Enums\TipoServicio;
use App\Models\OrdenJob;
use App\Models\personals;
use App\Models\reportes;
use App\Models\User;
use App\Support\ArchivoUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// ── Helpers ──────────────────────────────────────────────────────────────────

function crearPermiso(string $nombre): Permission
{
    return Permission::firstOrCreate(['name' => $nombre, 'guard_name' => 'web']);
}

function tecnicoSeguridad(): array
{
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    return [$user, $token];
}

// ── Vuln 1: InformeReporte solo accesible con permiso 'informes' ─────────────

test('POST /api/informes/reporte devuelve 403 sin permiso informes', function () {
    [, $token] = tecnicoSeguridad();

    $this->withToken($token)
        ->postJson('/api/informes/reporte')
        ->assertForbidden();
});

test('POST /api/informes/reporte acepta usuario con permiso informes', function () {
    [$user, $token] = tecnicoSeguridad();
    $user->givePermissionTo(crearPermiso('informes'));

    $this->withToken($token)
        ->postJson('/api/informes/reporte', [])
        ->assertSuccessful();
});

// ── Vuln 2: ArchivoController valida prefijo company_id ──────────────────────

test('GET /api/archivos rechaza path de otro tenant', function () {
    [, $token] = tecnicoSeguridad();
    $otroCompanyId = Str::uuid()->toString();

    $this->withToken($token)
        ->getJson("/api/archivos/{$otroCompanyId}/foto.jpg")
        ->assertForbidden();
});

test('GET /api/archivos rechaza path sin prefijo de company_id', function () {
    [, $token] = tecnicoSeguridad();

    $this->withToken($token)
        ->getJson('/api/archivos/reportes/foto.jpg')
        ->assertForbidden();
});

test('GET /api/archivos permite path del propio tenant', function () {
    Storage::fake('s3');
    [$user, $token] = tecnicoSeguridad();

    $companyId = $user->company_id;
    Storage::disk('s3')->put("{$companyId}/foto.jpg", 'contenido');

    $response = $this->withToken($token)
        ->getJson("/api/archivos/{$companyId}/foto.jpg");

    expect($response->status())->not->toBe(403);
});

test('GET /api/archivos rechaza traversal con .. para escapar al tenant propio', function () {
    Storage::fake('s3');
    [$user, $token] = tecnicoSeguridad();
    $otroCompanyId = Str::uuid()->toString();

    Storage::disk('s3')->put("{$otroCompanyId}/secreto.jpg", 'contenido de otra empresa');

    $this->withToken($token)
        ->getJson("/api/archivos/{$user->company_id}/../{$otroCompanyId}/secreto.jpg")
        ->assertForbidden();
});

// ── Vuln 6: operaciones/foto exige firma válida ───────────────────────────────

test('GET operaciones/foto sin firma válida devuelve 403', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(crearPermiso('evidencias.ver'));

    $this->actingAs($user)
        ->get('/operaciones/foto?path='.urlencode("{$user->company_id}/reportes/foto.jpg"))
        ->assertForbidden();
});

test('GET operaciones/foto con firma válida no devuelve 403 por firma', function () {
    Storage::fake('s3');
    $user = User::factory()->create();
    $user->givePermissionTo(crearPermiso('evidencias.ver'));

    $path = "{$user->company_id}/reportes/foto.jpg";
    Storage::disk('s3')->put($path, 'contenido');

    $url = ArchivoUrl::temporal($path);

    $response = $this->actingAs($user)->get($url);

    expect($response->getStatusCode())->not->toBe(403);
});

// ── Vuln 3: UploadController fuerza prefijo company_id ───────────────────────

test('POST /api/upload almacena archivo con prefijo company_id', function () {
    Storage::fake('s3');
    [$user, $token] = tecnicoSeguridad();

    $response = $this->withToken($token)->postJson('/api/upload', [
        'archivo' => UploadedFile::fake()->image('foto.jpg'),
        'directorio' => 'reportes/fotos',
    ]);

    $response->assertCreated();
    expect($response->json('path'))->toStartWith($user->company_id.'/');
});

test('POST /api/upload no puede escribir en namespace de otro tenant', function () {
    Storage::fake('s3');
    [$user, $token] = tecnicoSeguridad();

    $otroCompanyId = Str::uuid()->toString();

    $response = $this->withToken($token)->postJson('/api/upload', [
        'archivo' => UploadedFile::fake()->image('foto.jpg'),
        'directorio' => "{$otroCompanyId}/reportes",
    ]);

    $response->assertCreated();

    $path = $response->json('path');
    expect($path)->toStartWith($user->company_id.'/')
        ->and($path)->not->toStartWith("{$otroCompanyId}/");
});

// ── Vuln 4: ReporteController Vigilancia requiere personals_id ────────────────

test('POST reporte Vigilancia devuelve 403 si el usuario no tiene personals_id ni permiso', function () {
    $user = User::factory()->create(['personals_id' => null]);
    $token = $user->createToken('test')->plainTextToken;

    $orden = OrdenJob::factory()->create([
        'company_id' => $user->company_id,
        'tipo_servicio' => TipoServicio::Vigilancia->value,
        'estado' => EstadoOrden::EnProgreso->value,
    ]);

    $this->withToken($token)
        ->postJson("/api/tecnico/ordenes/{$orden->id}/reporte", [
            'fecha_turno' => now()->toDateString(),
            'hora_inicio' => '08:00',
            'hora_fin' => '16:00',
        ])
        ->assertForbidden();
});

// ── Vuln 5: GET ordenes/{orden}/reporte solo accesible al técnico dueño o con permiso ──

test('GET reporte devuelve 403 si el usuario no es el técnico asignado ni tiene permiso', function () {
    [, $token] = tecnicoSeguridad();

    $orden = OrdenJob::factory()->create([
        'tipo_servicio' => TipoServicio::Fraudes->value,
        'estado' => EstadoOrden::Rechazada->value,
    ]);

    reportes::create([
        'orden_job_id' => $orden->id,
        'personals_id' => $orden->personals_id,
        'numero_orden' => $orden->numero_orden,
    ]);

    $this->withToken($token)
        ->getJson("/api/tecnico/ordenes/{$orden->id}/reporte")
        ->assertForbidden();
});

// ── Vuln 7: throttle dedicado en /api/auth/login ──────────────────────────────

test('POST /api/auth/login bloquea después de 5 intentos fallidos en el mismo minuto', function () {
    $user = User::factory()->create(['password' => bcrypt('password')]);

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'incorrecta',
        ])->assertStatus(401);
    }

    $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertStatus(429);
});

test('GET reporte devuelve los datos del reporte al técnico dueño de la orden', function () {
    $personal = personals::create([
        'tipo_documento' => 'CC',
        'numero_documento' => fake()->unique()->numerify('##########'),
        'nombres' => 'Ana',
        'apellidos' => 'Prueba',
        'telefono' => '3000000000',
        'cargo' => 'Técnico',
    ]);

    $user = User::factory()->create(['personals_id' => $personal->id]);
    $token = $user->createToken('test')->plainTextToken;

    $orden = OrdenJob::factory()->create([
        'company_id' => $user->company_id,
        'personals_id' => $personal->id,
        'tipo_servicio' => TipoServicio::Fraudes->value,
        'estado' => EstadoOrden::Rechazada->value,
    ]);

    reportes::create([
        'company_id' => $user->company_id,
        'orden_job_id' => $orden->id,
        'personals_id' => $personal->id,
        'numero_orden' => $orden->numero_orden,
        'observaciones' => 'Observación de prueba.',
    ]);

    $this->withToken($token)
        ->getJson("/api/tecnico/ordenes/{$orden->id}/reporte")
        ->assertSuccessful()
        ->assertJsonPath('data.observaciones', 'Observación de prueba.');
});
