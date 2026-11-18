<?php

use App\Enums\EstadoOrden;
use App\Enums\TipoServicio;
use App\Livewire\Tecnico\ReporteCreate;
use App\Livewire\Tecnico\ReporteEdit;
use App\Models\OrdenJob;
use App\Models\personals;
use App\Models\reportes;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function ordenEmergenciaConTecnico(): array
{
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
        'tipo_auditoria_campo' => 'visita_calidad_emergencia',
        'estado' => EstadoOrden::EnProgreso->value,
    ]);

    return [$user, $orden];
}

/** @return array<string, mixed> */
function camposEmergenciaBase(array $extra = []): array
{
    return [
        'nombre_atiende' => 'Juan Perez',
        'fecha_hora_atencion' => now()->format('Y-m-d H:i:s'),
        'cedula_usuario' => '123456',
        'cedula_auditor' => '654321',
        'recibio_visita' => 1,
        'se_realizo_revision' => 0,
        'autoriza' => 1,
        ...$extra,
    ];
}

it('guarda las preguntas Si/No de emergencia como booleanos aunque queden con su valor por defecto', function () {
    Mail::fake();
    [$user, $orden] = ordenEmergenciaConTecnico();

    llenarEmergencia(Livewire::actingAs($user)->test(ReporteCreate::class, ['orden' => $orden]), ['recibio_visita' => 1, 'autoriza' => 1])
        ->call('submit')
        ->assertHasNoFormErrors();

    $datos = reportes::where('orden_job_id', $orden->id)->firstOrFail()->datos_servicio;

    expect($datos['recibio_visita'])->toBeTrue()
        ->and($datos['se_realizo_revision'])->toBeFalse()
        ->and($datos['se_detecto_fuga'])->toBeFalse()
        ->and($datos['problema_persiste'])->toBeFalse()
        ->and($datos['autoriza'])->toBeTrue();
});

it('permite responder No a si recibio la visita', function () {
    Mail::fake();
    [$user, $orden] = ordenEmergenciaConTecnico();

    Livewire::actingAs($user)
        ->test(ReporteCreate::class, ['orden' => $orden])
        ->fillForm(camposEmergenciaBase(['recibio_visita' => 0]))
        ->call('submit')
        ->assertHasNoFormErrors();

    expect(reportes::where('orden_job_id', $orden->id)->firstOrFail()->datos_servicio['recibio_visita'])->toBeFalse();
});

it('exige que el usuario autorice la recoleccion de informacion', function () {
    [$user, $orden] = ordenEmergenciaConTecnico();

    Livewire::actingAs($user)
        ->test(ReporteCreate::class, ['orden' => $orden])
        ->fillForm(camposEmergenciaBase(['autoriza' => 0]))
        ->call('submit')
        ->assertHasFormErrors(['autoriza']);

    expect(reportes::where('orden_job_id', $orden->id)->exists())->toBeFalse();
});

/**
 * Llena solo los campos indicados con set() para conservar los valores por defecto del formulario,
 * a diferencia de fillForm() que reemplaza todo el estado.
 *
 * @param  array<string, mixed>  $respuestas
 */
function llenarEmergencia(Testable $componente, array $respuestas)
{
    foreach ([
        'nombre_atiende' => 'Juan Perez',
        'fecha_hora_atencion' => now()->format('Y-m-d H:i:s'),
        'cedula_usuario' => '123456',
        'cedula_auditor' => '654321',
        ...$respuestas,
    ] as $campo => $valor) {
        $componente->set("data.{$campo}", $valor);
    }

    return $componente;
}

it('abre las preguntas Si/No con No preseleccionado', function () {
    [$user, $orden] = ordenEmergenciaConTecnico();

    Livewire::actingAs($user)
        ->test(ReporteCreate::class, ['orden' => $orden])
        ->assertSet('data.recibio_visita', 0)
        ->assertSet('data.se_detecto_fuga', 0)
        ->assertSet('data.autoriza', 0);
});

it('carga en edicion las respuestas Si/No guardadas y las vuelve a guardar como booleanos', function () {
    Mail::fake();
    [$user, $orden] = ordenEmergenciaConTecnico();
    $orden->update(['estado' => EstadoOrden::Rechazada->value]);

    $reporte = reportes::create([
        'orden_job_id' => $orden->id,
        'personals_id' => $user->personals_id,
        'numero_orden' => $orden->numero_orden,
        'requiere_correccion' => true,
        'datos_servicio' => camposEmergenciaBase(['recibio_visita' => true, 'se_realizo_revision' => false, 'autoriza' => true]),
    ]);

    Livewire::actingAs($user)
        ->test(ReporteEdit::class, ['orden' => $orden->fresh()])
        ->assertSet('data.recibio_visita', 1)
        ->assertSet('data.se_realizo_revision', 0)
        ->call('submit')
        ->assertHasNoFormErrors();

    $datos = $reporte->fresh()->datos_servicio;

    expect($datos['recibio_visita'])->toBeTrue()
        ->and($datos['se_realizo_revision'])->toBeFalse()
        ->and($datos['autoriza'])->toBeTrue();
});

it('no muestra el campo manual de georreferencia y conserva el valor guardado al editar', function () {
    Mail::fake();
    [$user, $orden] = ordenEmergenciaConTecnico();
    $orden->update(['estado' => EstadoOrden::Rechazada->value]);

    $reporte = reportes::create([
        'orden_job_id' => $orden->id,
        'personals_id' => $user->personals_id,
        'numero_orden' => $orden->numero_orden,
        'requiere_correccion' => true,
        'geo_referencia' => 'https://maps.google.com/?q=4.6,-74.0',
        'datos_servicio' => camposEmergenciaBase(['recibio_visita' => true, 'autoriza' => true]),
    ]);

    Livewire::actingAs($user)
        ->test(ReporteCreate::class, ['orden' => $orden])
        ->assertDontSee('GeoReferencia');

    Livewire::actingAs($user)
        ->test(ReporteEdit::class, ['orden' => $orden->fresh()])
        ->assertDontSee('GeoReferencia')
        ->call('submit')
        ->assertHasNoFormErrors();

    expect($reporte->fresh()->geo_referencia)->toBe('https://maps.google.com/?q=4.6,-74.0');
});
