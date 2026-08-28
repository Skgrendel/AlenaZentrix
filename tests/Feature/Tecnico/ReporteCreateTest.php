<?php

use App\Enums\TipoServicio;
use App\Livewire\Tecnico\ReporteCreate;
use App\Models\OrdenJob;
use App\Models\personals;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('crear reporte descarta referencias temporales de livewire en imagenes y foto_detector_fuga', function () {
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
    ]);

    $component = Livewire::actingAs($user)
        ->test(ReporteCreate::class, ['orden' => $orden]);

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
