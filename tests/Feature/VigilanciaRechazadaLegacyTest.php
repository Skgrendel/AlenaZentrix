<?php

use App\Enums\EstadoOrden;
use App\Enums\TipoServicio;
use App\Livewire\Tecnico\MisOrdenesIndex;
use App\Models\OrdenJob;
use App\Models\personals;
use App\Models\reportes;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(PermissionsSeeder::class));

/** @return array{0: User, 1: OrdenJob} */
function vigilanciaRechazada(bool $conTurno = true, bool $turnoMarcado = false): array
{
    $personal = personals::create([
        'tipo_documento' => 'CC',
        'numero_documento' => fake()->unique()->numerify('##########'),
        'nombres' => 'Ana',
        'apellidos' => 'Prueba',
        'telefono' => '3000000000',
        'cargo' => 'Investigador',
    ]);

    $user = User::create([
        'personals_id' => $personal->id,
        'email' => fake()->unique()->safeEmail(),
        'email_verified_at' => now(),
        'password' => Hash::make('password'),
        'estado' => true,
    ]);
    $user->givePermissionTo(['mis-ordenes.ver', 'mis-ordenes.reportar', 'ordenes.ver']);

    $orden = OrdenJob::factory()->create(['tipo_servicio' => TipoServicio::Vigilancia->value]);
    // Una orden de Vigilancia nace En Progreso: el estado rechazado viene de datos del flujo anterior.
    $orden->update(['estado' => EstadoOrden::Rechazada->value]);

    if ($conTurno) {
        reportes::create([
            'orden_job_id' => $orden->id,
            'numero_orden' => $orden->numero_orden,
            'personals_id' => $personal->id,
            'requiere_correccion' => $turnoMarcado,
            'datos_servicio' => ['fecha_turno' => '2026-10-01', 'hora_inicio' => '08:00', 'hora_fin' => '16:00'],
        ]);
    }

    return [$user, $orden];
}

it('una vigilancia rechazada sin turnos no da 404 sino que vuelve a Mis Ordenes con un aviso', function () {
    [$user, $orden] = vigilanciaRechazada(conTurno: false);

    $this->actingAs($user)
        ->get(route('tecnico.ordenes.reporte.editar', $orden))
        ->assertRedirect(route('tecnico.mis-ordenes'));
});

it('una vigilancia rechazada con turnos sigue abriendo el formulario de correccion', function () {
    [$user, $orden] = vigilanciaRechazada();

    $this->actingAs($user)
        ->get(route('tecnico.ordenes.reporte.editar', $orden))
        ->assertOk();
});

it('el boton Editar Reporte de una vigilancia solo aparece si hay turnos marcados', function () {
    [$user, $orden] = vigilanciaRechazada(turnoMarcado: false);

    Livewire::actingAs($user)
        ->test(MisOrdenesIndex::class)
        ->assertTableActionHidden('editar_reporte', $orden);

    reportes::where('orden_job_id', $orden->id)->update(['requiere_correccion' => true]);

    Livewire::actingAs($user)
        ->test(MisOrdenesIndex::class)
        ->assertTableActionVisible('editar_reporte', $orden);
});

it('la migracion reabre las vigilancias rechazadas con sus turnos marcados para corregir', function () {
    [, $orden] = vigilanciaRechazada();
    [, $sinTurnos] = vigilanciaRechazada(conTurno: false);

    $migracion = require database_path('migrations/2026_10_29_120000_normalizar_vigilancia_rechazada.php');
    $migracion->up();

    expect($orden->fresh()->estado)->toBe(EstadoOrden::EnProgreso)
        ->and($sinTurnos->fresh()->estado)->toBe(EstadoOrden::EnProgreso)
        ->and(reportes::where('orden_job_id', $orden->id)->first()->requiere_correccion)->toBeTrue();
});
