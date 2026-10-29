<?php

use App\Enums\EstadoOrden;
use App\Enums\TipoServicio;
use App\Models\OrdenJob;
use App\Models\reportes;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

/** @return array{0: User, 1: string, 2: OrdenJob} */
function ordenVigilanciaConToken(): array
{
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $orden = OrdenJob::factory()->create([
        'company_id' => $user->company_id,
        'personals_id' => $user->personals_id,
        'tipo_servicio' => TipoServicio::Vigilancia->value,
        'estado' => EstadoOrden::EnProgreso->value,
    ]);

    return [$user, $token, $orden];
}

function turnoVigilancia(OrdenJob $orden, string $fecha, bool $requiereCorreccion = false, ?string $motivo = null): reportes
{
    return reportes::create([
        'orden_job_id' => $orden->id,
        'numero_orden' => $orden->numero_orden,
        'company_id' => $orden->company_id,
        'requiere_correccion' => $requiereCorreccion,
        'comentarios' => $motivo,
        'datos_servicio' => ['fecha_turno' => $fecha, 'hora_inicio' => '08:00', 'hora_fin' => '16:00'],
    ]);
}

test('la orden de vigilancia lista los turnos por corregir y permite editar el reporte', function () {
    [, $token, $orden] = ordenVigilanciaConToken();
    $turno = turnoVigilancia($orden, '2026-10-01', true, 'Falta la hora de salida real');
    turnoVigilancia($orden, '2026-10-02');

    $this->withToken($token)
        ->getJson("/api/tecnico/ordenes/{$orden->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data.turnos_por_corregir')
        ->assertJsonPath('data.turnos_por_corregir.0.id', $turno->id)
        ->assertJsonPath('data.turnos_por_corregir.0.fecha_turno', '2026-10-01')
        ->assertJsonPath('data.turnos_por_corregir.0.motivo', 'Falta la hora de salida real')
        ->assertJsonPath('data.acciones.puede_editar_reporte', true);
});

test('una orden de vigilancia sin turnos marcados no permite editar', function () {
    [, $token, $orden] = ordenVigilanciaConToken();
    turnoVigilancia($orden, '2026-10-01');

    $this->withToken($token)
        ->getJson("/api/tecnico/ordenes/{$orden->id}")
        ->assertOk()
        ->assertJsonCount(0, 'data.turnos_por_corregir')
        ->assertJsonPath('data.acciones.puede_editar_reporte', false);
});

test('el tecnico corrige un turno de vigilancia sin cambiar el estado de la orden', function () {
    Mail::fake();
    [, $token, $orden] = ordenVigilanciaConToken();
    $turno = turnoVigilancia($orden, '2026-10-01', true, 'Corregir horas');
    $otro = turnoVigilancia($orden, '2026-10-02');

    $this->withToken($token)
        ->putJson("/api/tecnico/ordenes/{$orden->id}/reportes/{$turno->id}", [
            'fecha_turno' => '2026-10-01',
            'hora_inicio' => '07:00',
            'hora_fin' => '15:00',
            'observaciones' => 'Horas ajustadas',
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Turno corregido correctamente.')
        ->assertJsonPath('reporte.requiere_correccion', false);

    $turno->refresh();

    expect($turno->requiere_correccion)->toBeFalse()
        ->and($turno->datos_servicio['hora_inicio'])->toBe('07:00')
        ->and($turno->observaciones)->toBe('Horas ajustadas')
        ->and($orden->fresh()->estado)->toBe(EstadoOrden::EnProgreso)
        ->and($otro->fresh()->datos_servicio['hora_inicio'])->toBe('08:00');
});

test('no se puede corregir un turno que no fue marcado', function () {
    [, $token, $orden] = ordenVigilanciaConToken();
    $turno = turnoVigilancia($orden, '2026-10-01');

    $this->withToken($token)
        ->putJson("/api/tecnico/ordenes/{$orden->id}/reportes/{$turno->id}", [
            'fecha_turno' => '2026-10-01',
            'hora_inicio' => '07:00',
            'hora_fin' => '15:00',
        ])
        ->assertStatus(422);
});

test('no se puede mover un turno corregido a la fecha de otro turno', function () {
    [, $token, $orden] = ordenVigilanciaConToken();
    $turno = turnoVigilancia($orden, '2026-10-01', true, 'Corregir');
    turnoVigilancia($orden, '2026-10-02');

    $this->withToken($token)
        ->putJson("/api/tecnico/ordenes/{$orden->id}/reportes/{$turno->id}", [
            'fecha_turno' => '2026-10-02',
            'hora_inicio' => '07:00',
            'hora_fin' => '15:00',
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Ya existe otro turno registrado con esa fecha.');
});

test('la correccion por turno solo aplica a ordenes de vigilancia', function () {
    [$user, $token] = [User::factory()->create(), null];
    $token = $user->createToken('test')->plainTextToken;
    $orden = OrdenJob::factory()->create([
        'company_id' => $user->company_id,
        'personals_id' => $user->personals_id,
        'tipo_servicio' => TipoServicio::Investigacion->value,
        'estado' => EstadoOrden::Rechazada->value,
    ]);
    $reporte = turnoVigilancia($orden, '2026-10-01', true);

    $this->withToken($token)
        ->putJson("/api/tecnico/ordenes/{$orden->id}/reportes/{$reporte->id}", [])
        ->assertStatus(422);
});

test('el tecnico consulta un turno concreto de vigilancia', function () {
    [, $token, $orden] = ordenVigilanciaConToken();
    $turno = turnoVigilancia($orden, '2026-10-01', true, 'Corregir');

    $this->withToken($token)
        ->getJson("/api/tecnico/ordenes/{$orden->id}/reportes/{$turno->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $turno->id)
        ->assertJsonPath('data.requiere_correccion', true)
        ->assertJsonPath('data.comentarios', 'Corregir')
        ->assertJsonPath('data.datos_servicio.fecha_turno', '2026-10-01');
});
