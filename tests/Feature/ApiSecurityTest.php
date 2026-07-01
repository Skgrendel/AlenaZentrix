<?php

use App\Enums\EstadoOrden;
use App\Enums\TipoServicio;
use App\Models\OrdenJob;
use App\Models\User;
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
