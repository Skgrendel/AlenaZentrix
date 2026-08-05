<?php

use App\Enums\EstadoOrden;
use App\Enums\TipoServicio;
use App\Livewire\Tecnico\MisOrdenesIndex;
use App\Models\OrdenJob;
use App\Models\personals;
use App\Models\reportes;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function crearTecnico(): User
{
    $personal = personals::create([
        'tipo_documento' => 'CC',
        'numero_documento' => fake()->unique()->numerify('##########'),
        'nombres' => 'Ana',
        'apellidos' => 'Prueba',
        'telefono' => '3000000000',
        'cargo' => 'Técnico',
    ]);

    return User::create([
        'personals_id' => $personal->id,
        'email' => fake()->unique()->safeEmail(),
        'email_verified_at' => now(),
        'password' => Hash::make('password'),
        'estado' => true,
    ]);
}

test('muestra editar reporte para un turno de vigilancia rechazado aunque la orden siga en progreso', function () {
    $user = crearTecnico();

    $orden = OrdenJob::factory()->create([
        'personals_id' => $user->personals_id,
        'tipo_servicio' => TipoServicio::Vigilancia->value,
    ]);

    reportes::create([
        'orden_job_id' => $orden->id,
        'personals_id' => $user->personals_id,
        'numero_orden' => $orden->numero_orden,
        'requiere_correccion' => true,
    ]);

    expect($orden->fresh()->estado)->toBe(EstadoOrden::EnProgreso);

    Livewire::actingAs($user)
        ->test(MisOrdenesIndex::class)
        ->assertTableActionVisible('editar_reporte', $orden->fresh());
});

test('oculta editar reporte para vigilancia en progreso sin turnos marcados', function () {
    $user = crearTecnico();

    $orden = OrdenJob::factory()->create([
        'personals_id' => $user->personals_id,
        'tipo_servicio' => TipoServicio::Vigilancia->value,
    ]);

    reportes::create([
        'orden_job_id' => $orden->id,
        'personals_id' => $user->personals_id,
        'numero_orden' => $orden->numero_orden,
        'requiere_correccion' => false,
    ]);

    Livewire::actingAs($user)
        ->test(MisOrdenesIndex::class)
        ->assertTableActionHidden('editar_reporte', $orden->fresh());
});
