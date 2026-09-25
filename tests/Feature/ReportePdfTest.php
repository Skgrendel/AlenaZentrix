<?php

use App\Enums\EstadoAuditoria;
use App\Enums\EstadoOrden;
use App\Enums\TipoServicio;
use App\Http\Controllers\ReportePdfController;
use App\Models\auditoria;
use App\Models\OrdenJob;
use App\Models\reportes;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    ini_set('memory_limit', '256M');
    $this->seed(PermissionsSeeder::class);
});

test('guests are redirected to login on pdf download', function () {
    $orden = OrdenJob::factory()->create(['tipo_servicio' => TipoServicio::Fraudes->value]);

    $this->get(route('operaciones.ordenes-trabajo.reporte-fraude', $orden))
        ->assertRedirect(route('login'));
});

test('users without ordenes.ver permission cannot download pdf', function () {
    $user = User::factory()->create();
    $user->assignRole('Lector');

    $orden = OrdenJob::factory()->create(['tipo_servicio' => TipoServicio::Fraudes->value]);

    $this->actingAs($user)
        ->get(route('operaciones.ordenes-trabajo.reporte-fraude', $orden))
        ->assertForbidden();
});

test('pdf download returns 403 for non-fraudes service types', function () {
    $user = User::factory()->create();
    $user->assignRole('Coordinador');

    $orden = OrdenJob::factory()->create(['tipo_servicio' => TipoServicio::Investigacion->value]);

    $this->actingAs($user)
        ->get(route('operaciones.ordenes-trabajo.reporte-fraude', $orden))
        ->assertForbidden();
});

test('pdf download returns 404 when no reporte exists', function () {
    $user = User::factory()->create();
    $user->assignRole('Coordinador');

    $orden = OrdenJob::factory()->create([
        'tipo_servicio' => TipoServicio::Fraudes->value,
        'estado' => EstadoOrden::EnRevision->value,
    ]);

    $this->actingAs($user)
        ->get(route('operaciones.ordenes-trabajo.reporte-fraude', $orden))
        ->assertNotFound();
});

test('pdf gestion-legal returns 403 for non-gestion-legal orders', function () {
    $user = User::factory()->create();
    $user->assignRole('Coordinador');

    $orden = OrdenJob::factory()->create(['tipo_servicio' => TipoServicio::Fraudes->value]);

    $this->actingAs($user)
        ->get(route('operaciones.ordenes-trabajo.reporte-gestion-legal', $orden))
        ->assertForbidden();
});

test('pdf gestion-legal returns 404 when no reporte exists', function () {
    $user = User::factory()->create();
    $user->assignRole('Coordinador');

    $orden = OrdenJob::factory()->create([
        'tipo_servicio' => TipoServicio::GestionLegal->value,
        'estado' => EstadoOrden::EnRevision->value,
    ]);

    $this->actingAs($user)
        ->get(route('operaciones.ordenes-trabajo.reporte-gestion-legal', $orden))
        ->assertNotFound();
});

test('guests are redirected to login on gestion-legal pdf download', function () {
    $orden = OrdenJob::factory()->create(['tipo_servicio' => TipoServicio::GestionLegal->value]);

    $this->get(route('operaciones.ordenes-trabajo.reporte-gestion-legal', $orden))
        ->assertRedirect(route('login'));
});

test('coordinador can download pdf for fraudes reporte', function () {
    Storage::fake('s3');

    $user = User::factory()->create();
    $user->assignRole('Coordinador');

    $orden = OrdenJob::factory()->create([
        'tipo_servicio' => TipoServicio::Fraudes->value,
        'estado' => EstadoOrden::EnRevision->value,
        'creado_por_id' => $user->id,
        'company_id' => $user->company_id,
    ]);

    reportes::create([
        'orden_job_id' => $orden->id,
        'numero_orden' => $orden->numero_orden,
        'company_id' => $user->company_id,
        'imagenes' => [],
        'observaciones' => 'Sin novedad.',
    ]);

    $response = $this->actingAs($user)
        ->get(route('operaciones.ordenes-trabajo.reporte-fraude', $orden));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
});

test('coordinador can download pdf for auditoria campo medidores digitales reporte', function () {
    Storage::fake('s3');

    $user = User::factory()->create();
    $user->assignRole('Coordinador');

    $orden = OrdenJob::factory()->create([
        'tipo_servicio' => TipoServicio::AuditoriaCampo->value,
        'tipo_auditoria_campo' => 'visita_calidad_medidores_digitales',
        'estado' => EstadoOrden::EnRevision->value,
        'creado_por_id' => $user->id,
        'company_id' => $user->company_id,
    ]);

    reportes::create([
        'orden_job_id' => $orden->id,
        'numero_orden' => $orden->numero_orden,
        'company_id' => $user->company_id,
        'imagenes' => [],
        'datos_servicio' => [
            'fecha_hora_atencion' => '2026-05-29 10:00',
            'nombre_atiende' => 'María García',
            'tecnico_atendio_medidor' => 'Juan Pérez',
            'numero_medidor_instalado' => 'MD-999',
            'resultado_medidor' => 'conforme',
        ],
    ]);

    $response = $this->actingAs($user)
        ->get(route('operaciones.ordenes-trabajo.reporte-auditoria-campo', $orden));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
});

test('coordinador can download pdf for auditoria campo emergencia reporte', function () {
    Storage::fake('s3');

    $user = User::factory()->create();
    $user->assignRole('Coordinador');

    $orden = OrdenJob::factory()->create([
        'tipo_servicio' => TipoServicio::AuditoriaCampo->value,
        'tipo_auditoria_campo' => 'visita_calidad_emergencia',
        'estado' => EstadoOrden::EnRevision->value,
        'creado_por_id' => $user->id,
        'company_id' => $user->company_id,
    ]);

    reportes::create([
        'orden_job_id' => $orden->id,
        'numero_orden' => $orden->numero_orden,
        'company_id' => $user->company_id,
        'imagenes' => [],
        'datos_servicio' => [
            'nombre_atiende' => 'María García',
            'recibio_visita' => true,
        ],
    ]);

    $response = $this->actingAs($user)
        ->get(route('operaciones.ordenes-trabajo.reporte-auditoria-campo', $orden));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
});

test('guests are redirected to login on vigilancia pdf download', function () {
    $orden = OrdenJob::factory()->create(['tipo_servicio' => TipoServicio::Vigilancia->value]);

    $this->get(route('operaciones.ordenes-trabajo.reporte-vigilancia', $orden))
        ->assertRedirect(route('login'));
});

test('pdf vigilancia returns 403 for non-vigilancia service types', function () {
    $user = User::factory()->create();
    $user->assignRole('Coordinador');

    $orden = OrdenJob::factory()->create(['tipo_servicio' => TipoServicio::Investigacion->value]);

    $this->actingAs($user)
        ->get(route('operaciones.ordenes-trabajo.reporte-vigilancia', $orden))
        ->assertForbidden();
});

test('user without informes permission cannot download vigilancia pdf', function () {
    $user = User::factory()->create();
    $user->assignRole('Lector');

    $orden = OrdenJob::factory()->create(['tipo_servicio' => TipoServicio::Vigilancia->value]);

    $this->actingAs($user)
        ->get(route('operaciones.ordenes-trabajo.reporte-vigilancia', $orden))
        ->assertForbidden();
});

test('coordinador can download vigilancia pdf with no reportes', function () {
    Storage::fake('s3');

    $user = User::factory()->create();
    $user->assignRole('Coordinador');

    $orden = OrdenJob::factory()->create([
        'tipo_servicio' => TipoServicio::Vigilancia->value,
        'company_id' => $user->company_id,
    ]);

    $response = $this->actingAs($user)
        ->get(route('operaciones.ordenes-trabajo.reporte-vigilancia', $orden));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
});

test('resolveImagenes downscales large photos before embedding them in a pdf', function () {
    // A full-resolution phone photo (12MP) previously got embedded as-is, and dompdf
    // exhausted the 256M memory_limit decoding several of these in a multi-turno report.
    $largeImage = imagecreatetruecolor(4032, 3024);
    imagefilledrectangle($largeImage, 0, 0, 4032, 3024, imagecolorallocate($largeImage, 90, 140, 200));
    ob_start();
    imagejpeg($largeImage, null, 95);
    $original = ob_get_clean();
    imagedestroy($largeImage);

    $method = new ReflectionMethod(ReportePdfController::class, 'downscaleImageToBase64');
    $method->setAccessible(true);
    $dataUri = $method->invoke(new ReportePdfController, $original);

    expect($dataUri)->toStartWith('data:image/jpeg;base64,');

    $decoded = base64_decode(substr($dataUri, strlen('data:image/jpeg;base64,')));
    $resized = imagecreatefromstring($decoded);

    expect(max(imagesx($resized), imagesy($resized)))->toBeLessThanOrEqual(1200)
        ->and(strlen($decoded))->toBeLessThan(strlen($original));

    imagedestroy($resized);
});

test('coordinador can download fraude pdf with jpe evidencia photos on the auditoria', function () {
    Storage::fake('s3');

    $user = User::factory()->create();
    $user->assignRole('Coordinador');

    $orden = OrdenJob::factory()->create([
        'tipo_servicio' => TipoServicio::Fraudes->value,
        'estado' => EstadoOrden::EnRevision->value,
        'creado_por_id' => $user->id,
        'company_id' => $user->company_id,
    ]);

    $reporte = reportes::create([
        'orden_job_id' => $orden->id,
        'numero_orden' => $orden->numero_orden,
        'company_id' => $user->company_id,
        'imagenes' => [],
        'observaciones' => 'Sin novedad.',
    ]);

    $image = imagecreatetruecolor(10, 10);
    ob_start();
    imagejpeg($image);
    $imageContent = ob_get_clean();
    imagedestroy($image);

    Storage::disk('s3')->put('auditorias/evidencia.JPE', $imageContent);

    auditoria::create([
        'reportes_id' => $reporte->id,
        'estado' => EstadoAuditoria::Negativo,
        'evidencias' => ['auditorias/evidencia.JPE'],
        'observaciones' => 'Sin anomalías.',
    ]);

    $response = $this->actingAs($user)
        ->get(route('operaciones.ordenes-trabajo.reporte-fraude', $orden));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
});

test('coordinador can download fraude pdf when auditoria evidencias has an unresolved livewire upload reference', function () {
    Storage::fake('s3');

    $user = User::factory()->create();
    $user->assignRole('Coordinador');

    $orden = OrdenJob::factory()->create([
        'tipo_servicio' => TipoServicio::Fraudes->value,
        'estado' => EstadoOrden::EnRevision->value,
        'creado_por_id' => $user->id,
        'company_id' => $user->company_id,
    ]);

    $reporte = reportes::create([
        'orden_job_id' => $orden->id,
        'numero_orden' => $orden->numero_orden,
        'company_id' => $user->company_id,
        'imagenes' => [],
        'observaciones' => 'Sin novedad.',
    ]);

    // Un archivo que quedó a medio subir guarda la referencia temporal de Livewire
    // en vez de una ruta real de S3; nunca debería listarse como si fuera un documento.
    auditoria::create([
        'reportes_id' => $reporte->id,
        'estado' => EstadoAuditoria::Negativo,
        'evidencias' => ['auditorias/evidencia.jpg', 'livewire-file:cBgjMvKLVl6iY4oNK3HlZ5AM6JilPLBuTibZ'],
        'observaciones' => 'Sin anomalías.',
    ]);

    $response = $this->actingAs($user)
        ->get(route('operaciones.ordenes-trabajo.reporte-fraude', $orden));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
});

test('isImagePath recognizes jpe evidencia photos as images', function () {
    // Fotos de evidencias de auditoría suben con extensión .JPE y antes caían en la lista
    // de "documentos" del reporte en lugar de renderizarse como imagen.
    $method = new ReflectionMethod(ReportePdfController::class, 'isImagePath');
    $method->setAccessible(true);
    $controller = new ReportePdfController;

    expect($method->invoke($controller, '01M37XP43ES2M2XZWC9BKW14NS.JPE'))->toBeTrue()
        ->and($method->invoke($controller, 'evidencia.jpe'))->toBeTrue()
        ->and($method->invoke($controller, 'evidencia.jpg'))->toBeTrue()
        ->and($method->invoke($controller, 'evidencia.jpeg'))->toBeTrue()
        ->and($method->invoke($controller, 'documento.pdf'))->toBeFalse();
});

test('coordinador can download vigilancia pdf with multiple reportes', function () {
    Storage::fake('s3');

    $user = User::factory()->create();
    $user->assignRole('Coordinador');

    $orden = OrdenJob::factory()->create([
        'tipo_servicio' => TipoServicio::Vigilancia->value,
        'company_id' => $user->company_id,
    ]);

    reportes::create([
        'orden_job_id' => $orden->id,
        'numero_orden' => $orden->numero_orden,
        'company_id' => $user->company_id,
        'imagenes' => [],
        'observaciones' => 'Sin novedad en el turno.',
        'datos_servicio' => [
            'fecha_turno' => '2026-07-01',
            'hora_inicio' => '08:00',
            'hora_fin' => '16:00',
            'hay_novedad' => false,
            'hay_reincidencia' => false,
        ],
    ]);

    reportes::create([
        'orden_job_id' => $orden->id,
        'numero_orden' => $orden->numero_orden,
        'company_id' => $user->company_id,
        'imagenes' => [],
        'observaciones' => 'Se detectó novedad.',
        'datos_servicio' => [
            'fecha_turno' => '2026-07-02',
            'hora_inicio' => '16:00',
            'hora_fin' => '00:00',
            'hay_novedad' => true,
            'descripcion_novedad' => 'Persona sospechosa en el perímetro.',
            'hay_reincidencia' => true,
        ],
    ]);

    $response = $this->actingAs($user)
        ->get(route('operaciones.ordenes-trabajo.reporte-vigilancia', $orden));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
});
