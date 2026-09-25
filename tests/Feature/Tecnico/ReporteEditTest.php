<?php

use App\Enums\EstadoOrden;
use App\Enums\TipoServicio;
use App\Livewire\Tecnico\ReporteEdit;
use App\Models\OrdenJob;
use App\Models\personals;
use App\Models\reportes;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('editar reporte no consulta metadatos de archivos al montar', function () {
    $personal = personals::create([
        'tipo_documento' => 'CC',
        'numero_documento' => fake()->unique()->numerify('##########'),
        'nombres' => 'Ana',
        'apellidos' => 'Prueba',
        'telefono' => '3000000000',
        'cargo' => 'Técnico',
    ]);

    $user = User::create([
        'personals_id' => $personal->id,
        'email' => fake()->unique()->safeEmail(),
        'email_verified_at' => now(),
        'password' => Hash::make('password'),
        'estado' => true,
    ]);

    $orden = OrdenJob::factory()->create([
        'personals_id' => $personal->id,
        'tipo_servicio' => TipoServicio::Fraudes->value,
        'estado' => EstadoOrden::Rechazada->value,
    ]);

    reportes::create([
        'orden_job_id' => $orden->id,
        'personals_id' => $personal->id,
        'numero_orden' => $orden->numero_orden,
        'imagenes' => ['reportes/'.$orden->numero_orden.'/01KRCHYRZFVBDDQCKKCS3K3NGW.png'],
        'observaciones' => 'Reporte con imagen previa.',
    ]);

    Storage::shouldReceive('disk')
        ->with('s3')
        ->never();

    Livewire::actingAs($user)
        ->test(ReporteEdit::class, ['orden' => $orden])
        ->assertOk();
});

test('editar reporte muestra archivos sin previsualizacion', function () {
    $personal = personals::create([
        'tipo_documento' => 'CC',
        'numero_documento' => fake()->unique()->numerify('##########'),
        'nombres' => 'Ana',
        'apellidos' => 'Prueba',
        'telefono' => '3000000000',
        'cargo' => 'Técnico',
    ]);

    $user = User::create([
        'personals_id' => $personal->id,
        'email' => fake()->unique()->safeEmail(),
        'email_verified_at' => now(),
        'password' => Hash::make('password'),
        'estado' => true,
    ]);

    $orden = OrdenJob::factory()->create([
        'personals_id' => $personal->id,
        'tipo_servicio' => TipoServicio::Fraudes->value,
        'estado' => EstadoOrden::Rechazada->value,
    ]);

    $component = Livewire::actingAs($user)
        ->test(ReporteEdit::class, ['orden' => $orden]);

    $method = new ReflectionMethod($component->instance(), 'fraudesSchema');
    $method->setAccessible(true);

    $schema = $method->invoke($component->instance());

    $fileUploads = collect($schema)
        ->flatMap(fn ($section) => method_exists($section, 'getChildComponents') ? $section->getChildComponents() : [])
        ->filter(fn ($component) => method_exists($component, 'getName') && in_array($component->getName(), ['imagenes', 'foto_detector_fuga'], true));

    expect($fileUploads)->not->toBeEmpty();
    expect($fileUploads->every(fn ($component) => method_exists($component, 'isPreviewable') && ! $component->isPreviewable()))
        ->toBeTrue();
});

test('editar reporte carga y guarda actividades en servicios genericos', function () {
    $personal = personals::create([
        'tipo_documento' => 'CC',
        'numero_documento' => fake()->unique()->numerify('##########'),
        'nombres' => 'Ana',
        'apellidos' => 'Prueba',
        'telefono' => '3000000000',
        'cargo' => 'Técnico',
    ]);

    $user = User::create([
        'personals_id' => $personal->id,
        'email' => fake()->unique()->safeEmail(),
        'email_verified_at' => now(),
        'password' => Hash::make('password'),
        'estado' => true,
    ]);

    $orden = OrdenJob::factory()->create([
        'personals_id' => $personal->id,
        'tipo_servicio' => TipoServicio::Investigacion->value,
        'estado' => EstadoOrden::Rechazada->value,
    ]);

    $actividadUuid = (string) Str::uuid();

    $reporte = reportes::create([
        'orden_job_id' => $orden->id,
        'personals_id' => $personal->id,
        'numero_orden' => $orden->numero_orden,
        'observaciones' => 'Observacion inicial',
        'datos_servicio' => ['convencion_id' => 1],
        'actividades' => [
            [
                'uuid' => $actividadUuid,
                'descripcion_orden' => 'Actividad base',
                'actividades' => 'Respuesta inicial',
            ],
        ],
    ]);

    $component = Livewire::actingAs($user)
        ->test(ReporteEdit::class, ['orden' => $orden]);

    $method = new ReflectionMethod($component->instance(), 'submitServicioGenerico');
    $method->setAccessible(true);
    $method->invoke($component->instance(), [
        'observaciones' => 'Observacion corregida',
        'actividades' => [
            [
                'uuid' => $actividadUuid,
                'descripcion_orden' => 'Actividad base',
                'actividades' => 'Respuesta corregida',
            ],
        ],
    ]);

    expect(collect($reporte->refresh()->actividades)->first()['actividades'])->toBe('Respuesta corregida');
    expect($reporte->refresh()->requiere_correccion)->toBeFalse();
});

test('editar reporte carga el turno especifico marcado como requiere_correccion, no el mas reciente', function () {
    $personal = personals::create([
        'tipo_documento' => 'CC',
        'numero_documento' => fake()->unique()->numerify('##########'),
        'nombres' => 'Ana',
        'apellidos' => 'Prueba',
        'telefono' => '3000000000',
        'cargo' => 'Técnico',
    ]);

    $user = User::create([
        'personals_id' => $personal->id,
        'email' => fake()->unique()->safeEmail(),
        'email_verified_at' => now(),
        'password' => Hash::make('password'),
        'estado' => true,
    ]);

    // El modelo OrdenJob fuerza estado=EnProgreso al crear una orden de Vigilancia,
    // por eso el estado Rechazada se aplica en un update() posterior a la creación.
    $orden = OrdenJob::factory()->create([
        'personals_id' => $personal->id,
        'tipo_servicio' => TipoServicio::Vigilancia->value,
    ]);
    $orden->update(['estado' => EstadoOrden::Rechazada->value]);

    // 'created_at' no es fillable, se fuerza tras crear para simular que el turno
    // marcado como rechazado NO es el más reciente subido.
    $turnoRechazado = reportes::create([
        'orden_job_id' => $orden->id,
        'personals_id' => $personal->id,
        'numero_orden' => $orden->numero_orden,
        'datos_servicio' => ['fecha_turno' => '2026-07-10'],
        'requiere_correccion' => true,
    ]);
    $turnoRechazado->forceFill(['created_at' => now()->subDays(5)])->save();

    $turnoReciente = reportes::create([
        'orden_job_id' => $orden->id,
        'personals_id' => $personal->id,
        'numero_orden' => $orden->numero_orden,
        'datos_servicio' => ['fecha_turno' => '2026-07-20'],
        'requiere_correccion' => false,
    ]);
    $turnoReciente->forceFill(['created_at' => now()->subDay()])->save();

    $component = Livewire::actingAs($user)
        ->test(ReporteEdit::class, ['orden' => $orden]);

    expect($component->instance()->reporte->id)->toBe($turnoRechazado->id);
});

test('editar reporte usa el mas reciente si ningun reporte esta marcado (compatibilidad con datos previos al fix)', function () {
    $personal = personals::create([
        'tipo_documento' => 'CC',
        'numero_documento' => fake()->unique()->numerify('##########'),
        'nombres' => 'Ana',
        'apellidos' => 'Prueba',
        'telefono' => '3000000000',
        'cargo' => 'Técnico',
    ]);

    $user = User::create([
        'personals_id' => $personal->id,
        'email' => fake()->unique()->safeEmail(),
        'email_verified_at' => now(),
        'password' => Hash::make('password'),
        'estado' => true,
    ]);

    // El modelo OrdenJob fuerza estado=EnProgreso al crear una orden de Vigilancia,
    // por eso el estado Rechazada se aplica en un update() posterior a la creación.
    $orden = OrdenJob::factory()->create([
        'personals_id' => $personal->id,
        'tipo_servicio' => TipoServicio::Vigilancia->value,
    ]);
    $orden->update(['estado' => EstadoOrden::Rechazada->value]);

    // 'created_at' no es fillable, se fuerza tras crear para garantizar el orden.
    $masAntiguo = reportes::create([
        'orden_job_id' => $orden->id,
        'personals_id' => $personal->id,
        'numero_orden' => $orden->numero_orden,
        'requiere_correccion' => false,
    ]);
    $masAntiguo->forceFill(['created_at' => now()->subDays(5)])->save();

    $masReciente = reportes::create([
        'orden_job_id' => $orden->id,
        'personals_id' => $personal->id,
        'numero_orden' => $orden->numero_orden,
        'requiere_correccion' => false,
    ]);
    $masReciente->forceFill(['created_at' => now()->subDay()])->save();

    $component = Livewire::actingAs($user)
        ->test(ReporteEdit::class, ['orden' => $orden]);

    expect($component->instance()->reporte->id)->toBe($masReciente->id);
});

test('editar reporte muestra un selector cuando varios turnos de vigilancia requieren correccion', function () {
    $personal = personals::create([
        'tipo_documento' => 'CC',
        'numero_documento' => fake()->unique()->numerify('##########'),
        'nombres' => 'Ana',
        'apellidos' => 'Prueba',
        'telefono' => '3000000000',
        'cargo' => 'Técnico',
    ]);

    $user = User::create([
        'personals_id' => $personal->id,
        'email' => fake()->unique()->safeEmail(),
        'email_verified_at' => now(),
        'password' => Hash::make('password'),
        'estado' => true,
    ]);

    // La orden de Vigilancia arranca y permanece En Progreso: el rechazo puntual de un
    // turno ya no bloquea toda la orden.
    $orden = OrdenJob::factory()->create([
        'personals_id' => $personal->id,
        'tipo_servicio' => TipoServicio::Vigilancia->value,
    ]);

    $turno1 = reportes::create([
        'orden_job_id' => $orden->id,
        'personals_id' => $personal->id,
        'numero_orden' => $orden->numero_orden,
        'comentarios' => 'Falta la descripción de la novedad.',
        'requiere_correccion' => true,
    ]);

    $turno2 = reportes::create([
        'orden_job_id' => $orden->id,
        'personals_id' => $personal->id,
        'numero_orden' => $orden->numero_orden,
        'comentarios' => 'La hora de inicio no coincide con el turno.',
        'requiere_correccion' => true,
    ]);

    $component = Livewire::actingAs($user)
        ->test(ReporteEdit::class, ['orden' => $orden]);

    expect($orden->fresh()->estado)->toBe(EstadoOrden::EnProgreso)
        ->and($component->instance()->reporte)->toBeNull()
        ->and($component->instance()->turnosPendientes)->not->toBeNull()
        ->and($component->instance()->turnosPendientes->pluck('id')->sort()->values()->all())
        ->toBe(collect([$turno1->id, $turno2->id])->sort()->values()->all());
});

test('editar reporte carga el turno elegido por query param cuando hay varios pendientes', function () {
    $personal = personals::create([
        'tipo_documento' => 'CC',
        'numero_documento' => fake()->unique()->numerify('##########'),
        'nombres' => 'Ana',
        'apellidos' => 'Prueba',
        'telefono' => '3000000000',
        'cargo' => 'Técnico',
    ]);

    $user = User::create([
        'personals_id' => $personal->id,
        'email' => fake()->unique()->safeEmail(),
        'email_verified_at' => now(),
        'password' => Hash::make('password'),
        'estado' => true,
    ]);

    $orden = OrdenJob::factory()->create([
        'personals_id' => $personal->id,
        'tipo_servicio' => TipoServicio::Vigilancia->value,
    ]);

    reportes::create([
        'orden_job_id' => $orden->id,
        'personals_id' => $personal->id,
        'numero_orden' => $orden->numero_orden,
        'requiere_correccion' => true,
    ]);

    $turno2 = reportes::create([
        'orden_job_id' => $orden->id,
        'personals_id' => $personal->id,
        'numero_orden' => $orden->numero_orden,
        'requiere_correccion' => true,
    ]);

    $component = Livewire::actingAs($user)
        ->withQueryParams(['reporte' => $turno2->id])
        ->test(ReporteEdit::class, ['orden' => $orden]);

    expect($component->instance()->reporte->id)->toBe($turno2->id)
        ->and($component->instance()->turnosPendientes)->toBeNull();
});

test('editar reporte descarta referencias temporales de livewire en imagenes y foto_detector_fuga', function () {
    $personal = personals::create([
        'tipo_documento' => 'CC',
        'numero_documento' => fake()->unique()->numerify('##########'),
        'nombres' => 'Ana',
        'apellidos' => 'Prueba',
        'telefono' => '3000000000',
        'cargo' => 'Técnico',
    ]);

    $user = User::create([
        'personals_id' => $personal->id,
        'email' => fake()->unique()->safeEmail(),
        'email_verified_at' => now(),
        'password' => Hash::make('password'),
        'estado' => true,
    ]);

    $orden = OrdenJob::factory()->create([
        'personals_id' => $personal->id,
        'tipo_servicio' => TipoServicio::Fraudes->value,
        'estado' => EstadoOrden::Rechazada->value,
    ]);

    reportes::create([
        'orden_job_id' => $orden->id,
        'personals_id' => $personal->id,
        'numero_orden' => $orden->numero_orden,
    ]);

    $component = Livewire::actingAs($user)
        ->test(ReporteEdit::class, ['orden' => $orden]);

    $method = new ReflectionMethod($component->instance(), 'descartarReferenciasTemporales');
    $method->setAccessible(true);

    // Un borrador restaurado a medio subir deja una referencia "livewire-file:..." mezclada
    // con rutas reales de S3; solo las rutas reales deben sobrevivir.
    expect($method->invoke($component->instance(), [
        'reportes/OT-1/foto-real.jpg',
        'livewire-file:cBgjMvKLVl6iY4oNK3HlZ5AM6JilPLBuTibZ',
    ]))->toBe(['reportes/OT-1/foto-real.jpg']);

    expect($method->invoke($component->instance(), 'livewire-file:cBgjMvKLVl6iY4oNK3HlZ5AM6JilPLBuTibZ'))->toBeNull();
    expect($method->invoke($component->instance(), 'reportes/OT-1/foto-real.jpg'))->toBe('reportes/OT-1/foto-real.jpg');
    expect($method->invoke($component->instance(), null))->toBeNull();
});

test('editar reporte de vigilancia corrige solo el turno puntual sin afectar el estado de la orden ni otros turnos', function () {
    $personal = personals::create([
        'tipo_documento' => 'CC',
        'numero_documento' => fake()->unique()->numerify('##########'),
        'nombres' => 'Ana',
        'apellidos' => 'Prueba',
        'telefono' => '3000000000',
        'cargo' => 'Técnico',
    ]);

    $user = User::create([
        'personals_id' => $personal->id,
        'email' => fake()->unique()->safeEmail(),
        'email_verified_at' => now(),
        'password' => Hash::make('password'),
        'estado' => true,
    ]);

    $orden = OrdenJob::factory()->create([
        'personals_id' => $personal->id,
        'tipo_servicio' => TipoServicio::Vigilancia->value,
    ]);

    $turnoRechazado = reportes::create([
        'orden_job_id' => $orden->id,
        'personals_id' => $personal->id,
        'numero_orden' => $orden->numero_orden,
        'requiere_correccion' => true,
        'comentarios' => 'Falta la hora de fin del turno.',
    ]);

    $otroTurno = reportes::create([
        'orden_job_id' => $orden->id,
        'personals_id' => $personal->id,
        'numero_orden' => $orden->numero_orden,
        'requiere_correccion' => false,
    ]);

    $component = Livewire::actingAs($user)
        ->withQueryParams(['reporte' => $turnoRechazado->id])
        ->test(ReporteEdit::class, ['orden' => $orden]);

    $method = new ReflectionMethod($component->instance(), 'submitServicioGenerico');
    $method->setAccessible(true);
    $method->invoke($component->instance(), [
        'observaciones' => 'Turno corregido con la hora de fin correcta.',
    ]);

    expect($turnoRechazado->fresh()->requiere_correccion)->toBeFalse()
        ->and($otroTurno->fresh()->requiere_correccion)->toBeFalse()
        ->and($orden->fresh()->estado)->toBe(EstadoOrden::EnProgreso);
});

test('editar reporte muestra las fotos ya guardadas en una galeria separada del campo de subida', function () {
    $personal = personals::create([
        'tipo_documento' => 'CC',
        'numero_documento' => fake()->unique()->numerify('##########'),
        'nombres' => 'Ana',
        'apellidos' => 'Prueba',
        'telefono' => '3000000000',
        'cargo' => 'Técnico',
    ]);

    $user = User::create([
        'personals_id' => $personal->id,
        'email' => fake()->unique()->safeEmail(),
        'email_verified_at' => now(),
        'password' => Hash::make('password'),
        'estado' => true,
    ]);

    $orden = OrdenJob::factory()->create([
        'personals_id' => $personal->id,
        'tipo_servicio' => TipoServicio::Fraudes->value,
        'estado' => EstadoOrden::Rechazada->value,
    ]);

    reportes::create([
        'orden_job_id' => $orden->id,
        'personals_id' => $personal->id,
        'numero_orden' => $orden->numero_orden,
        'imagenes' => [
            'reportes/'.$orden->numero_orden.'/foto-1.jpg',
            'reportes/'.$orden->numero_orden.'/foto-2.jpg',
        ],
        'observaciones' => 'Reporte con fotos previas.',
    ]);

    $component = Livewire::actingAs($user)
        ->test(ReporteEdit::class, ['orden' => $orden]);

    $urls = $component->instance()->imagenesExistentesUrls();

    // La URL firmada se regenera en cada render (el timestamp de expiración cambia),
    // así que comparamos por la ruta del recurso en vez de la firma exacta. Cada foto
    // aparece dos veces en el HTML: en el atributo @click del lightbox y en el <img>.
    $html = $component->html();

    expect($urls)->toHaveCount(2)
        ->and($urls[0])->toContain('/operaciones/foto')
        ->and(substr_count($html, 'operaciones/foto'))->toBe(4)
        ->and($html)->toContain('8. Fotos ya Guardadas');
});

test('editar reporte descarta referencias temporales de livewire en fotos existentes', function () {
    $personal = personals::create([
        'tipo_documento' => 'CC',
        'numero_documento' => fake()->unique()->numerify('##########'),
        'nombres' => 'Ana',
        'apellidos' => 'Prueba',
        'telefono' => '3000000000',
        'cargo' => 'Técnico',
    ]);

    $user = User::create([
        'personals_id' => $personal->id,
        'email' => fake()->unique()->safeEmail(),
        'email_verified_at' => now(),
        'password' => Hash::make('password'),
        'estado' => true,
    ]);

    $orden = OrdenJob::factory()->create([
        'personals_id' => $personal->id,
        'tipo_servicio' => TipoServicio::Fraudes->value,
        'estado' => EstadoOrden::Rechazada->value,
    ]);

    reportes::create([
        'orden_job_id' => $orden->id,
        'personals_id' => $personal->id,
        'numero_orden' => $orden->numero_orden,
        'imagenes' => [
            'reportes/'.$orden->numero_orden.'/foto-1.jpg',
            'livewire-file:cBgjMvKLVl6iY4oNK3HlZ5AM6JilPLBuTibZ',
        ],
        'observaciones' => 'Reporte con una foto que no llegó a subirse.',
    ]);

    $component = Livewire::actingAs($user)
        ->test(ReporteEdit::class, ['orden' => $orden]);

    expect($component->instance()->imagenesExistentes)->toBe([
        'reportes/'.$orden->numero_orden.'/foto-1.jpg',
    ]);
});

test('editar reporte sin fotos previas no muestra la seccion de fotos ya guardadas', function () {
    $personal = personals::create([
        'tipo_documento' => 'CC',
        'numero_documento' => fake()->unique()->numerify('##########'),
        'nombres' => 'Ana',
        'apellidos' => 'Prueba',
        'telefono' => '3000000000',
        'cargo' => 'Técnico',
    ]);

    $user = User::create([
        'personals_id' => $personal->id,
        'email' => fake()->unique()->safeEmail(),
        'email_verified_at' => now(),
        'password' => Hash::make('password'),
        'estado' => true,
    ]);

    $orden = OrdenJob::factory()->create([
        'personals_id' => $personal->id,
        'tipo_servicio' => TipoServicio::Fraudes->value,
        'estado' => EstadoOrden::Rechazada->value,
    ]);

    reportes::create([
        'orden_job_id' => $orden->id,
        'personals_id' => $personal->id,
        'numero_orden' => $orden->numero_orden,
        'observaciones' => 'Reporte sin fotos.',
    ]);

    Livewire::actingAs($user)
        ->test(ReporteEdit::class, ['orden' => $orden])
        ->assertDontSee('Fotos ya Guardadas');
});

test('editar reporte de auditoria de campo precarga comentario_usuario y comentario_tecnico desde la orden cuando el reporte aun no los tiene', function () {
    $personal = personals::create([
        'tipo_documento' => 'CC',
        'numero_documento' => fake()->unique()->numerify('##########'),
        'nombres' => 'Ana',
        'apellidos' => 'Prueba',
        'telefono' => '3000000000',
        'cargo' => 'Técnico',
    ]);

    $user = User::create([
        'personals_id' => $personal->id,
        'email' => fake()->unique()->safeEmail(),
        'email_verified_at' => now(),
        'password' => Hash::make('password'),
        'estado' => true,
    ]);

    $orden = OrdenJob::factory()->create([
        'personals_id' => $personal->id,
        'tipo_servicio' => TipoServicio::AuditoriaCampo->value,
        'estado' => EstadoOrden::Rechazada->value,
        'comentario_usuario' => 'El usuario solicitó revisión por olor a gas.',
        'comentario_tecnico' => 'Se revisó la instalación y no se encontraron fugas.',
    ]);

    reportes::create([
        'orden_job_id' => $orden->id,
        'personals_id' => $personal->id,
        'numero_orden' => $orden->numero_orden,
        'datos_servicio' => [
            'nombre_atiende' => 'Carlos Pérez',
        ],
        'comentarios' => 'Falta la firma del auditor.',
    ]);

    Livewire::actingAs($user)
        ->test(ReporteEdit::class, ['orden' => $orden])
        ->assertSet('data.comentario_usuario', 'El usuario solicitó revisión por olor a gas.')
        ->assertSet('data.comentario_tecnico', 'Se revisó la instalación y no se encontraron fugas.');
});

test('editar reporte de auditoria de campo respeta un comentario ya corregido en el reporte en vez del de la orden', function () {
    $personal = personals::create([
        'tipo_documento' => 'CC',
        'numero_documento' => fake()->unique()->numerify('##########'),
        'nombres' => 'Ana',
        'apellidos' => 'Prueba',
        'telefono' => '3000000000',
        'cargo' => 'Técnico',
    ]);

    $user = User::create([
        'personals_id' => $personal->id,
        'email' => fake()->unique()->safeEmail(),
        'email_verified_at' => now(),
        'password' => Hash::make('password'),
        'estado' => true,
    ]);

    $orden = OrdenJob::factory()->create([
        'personals_id' => $personal->id,
        'tipo_servicio' => TipoServicio::AuditoriaCampo->value,
        'estado' => EstadoOrden::Rechazada->value,
        'comentario_usuario' => 'Comentario original de la orden.',
        'comentario_tecnico' => 'Comentario técnico original de la orden.',
    ]);

    reportes::create([
        'orden_job_id' => $orden->id,
        'personals_id' => $personal->id,
        'numero_orden' => $orden->numero_orden,
        'datos_servicio' => [
            'nombre_atiende' => 'Carlos Pérez',
            'comentario_usuario' => 'Comentario ya corregido por el técnico.',
            'comentario_tecnico' => 'Comentario técnico ya corregido.',
        ],
        'comentarios' => 'Falta la firma del auditor.',
    ]);

    Livewire::actingAs($user)
        ->test(ReporteEdit::class, ['orden' => $orden])
        ->assertSet('data.comentario_usuario', 'Comentario ya corregido por el técnico.')
        ->assertSet('data.comentario_tecnico', 'Comentario técnico ya corregido.');
});
