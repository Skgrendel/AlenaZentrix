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
