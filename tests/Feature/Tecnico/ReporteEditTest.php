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
});
