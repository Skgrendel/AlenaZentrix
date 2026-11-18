<?php

use App\Enums\EstadoOrden;
use App\Enums\TipoServicio;
use App\Livewire\Tecnico\ReporteCreate;
use App\Livewire\Tecnico\ReporteEdit;
use App\Models\Company;
use App\Models\OrdenJob;
use App\Models\personals;
use App\Models\reportes;
use App\Models\TipoAnomalia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function suspensionesContexto(EstadoOrden $estado = EstadoOrden::EnProgreso): array
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
        'company_id' => Company::factory()->create()->id,
    ]);

    $orden = OrdenJob::factory()->create([
        'personals_id' => $personal->id,
        'tipo_servicio' => TipoServicio::AuditoriaCampo->value,
        'tipo_auditoria_campo' => 'visita_calidad_suspensiones',
        'estado' => $estado->value,
    ]);

    return [$user, $orden];
}

/** @return array<int, string> */
function fotosSuspensiones(string $prefijo, int $cantidad = 3): array
{
    return collect(range(1, $cantidad))->map(fn (int $i) => "reportes/OT/{$prefijo}-{$i}.jpg")->all();
}

/** @return array<string, mixed> */
function camposEjecutada(array $extra = []): array
{
    return [
        'categoria_campo' => 'residencial',
        'suspension_ejecutada' => 1,
        'lectura_medidor' => '123.45',
        'anomalias_medidor' => [],
        'condicion_sellos' => 'Buenos',
        'elementos_rotos' => 'Ninguno',
        'fotos_fachada' => fotosSuspensiones('f'),
        'fotos_suspension' => fotosSuspensiones('s'),
        'fotos_medidor' => fotosSuspensiones('m', 1),
        ...$extra,
    ];
}

/** @return array<string, mixed> */
function camposNoEjecutada(array $extra = []): array
{
    return [
        'categoria_campo' => 'industrial',
        'suspension_ejecutada' => 0,
        'motivo_no_ejecucion' => 'predio_cerrado',
        'justificacion_no_ejecucion' => 'Nadie atendio',
        'fotos_fachada' => fotosSuspensiones('f'),
        'fotos_no_ejecucion' => fotosSuspensiones('n', 1),
        ...$extra,
    ];
}

function crearSuspension(User $user, OrdenJob $orden, array $estado): void
{
    Livewire::actingAs($user)
        ->test(ReporteCreate::class, ['orden' => $orden])
        ->fillForm($estado)
        ->call('submit')
        ->assertHasNoFormErrors();
}

it('crea un reporte ejecutado con sonda y exige las series', function () {
    Mail::fake();
    [$user, $orden] = suspensionesContexto();
    $base = camposEjecutada(['metodo_suspension' => 'sonda_ips', 'observaciones' => 'Sin novedad']);

    Livewire::actingAs($user)
        ->test(ReporteCreate::class, ['orden' => $orden])
        ->fillForm($base)
        ->call('submit')
        ->assertHasFormErrors(['serie_sonda' => 'required', 'serie_caps' => 'required']);

    crearSuspension($user, $orden, [...$base, 'serie_sonda' => '12345', 'serie_caps' => '67890']);

    $reporte = reportes::where('orden_job_id', $orden->id)->firstOrFail();
    $datos = $reporte->datos_servicio;

    expect($datos['suspension_ejecutada'])->toBeTrue()
        ->and($datos['metodo_suspension'])->toBe('sonda_ips')
        ->and($datos['serie_sonda'])->toBe('12345')
        ->and($datos['serie_caps'])->toBe('67890')
        ->and($datos['lectura_medidor'])->toBe('123.45')
        ->and($datos['anomalias_medidor'] ?? [])->toBe([])
        ->and($datos['condicion_sellos'])->toBe('Buenos')
        ->and($datos['elementos_rotos'])->toBe('Ninguno')
        ->and($datos['fotos_fachada'])->toHaveCount(3)
        ->and($datos['fotos_suspension'])->toHaveCount(3)
        ->and($datos['fotos_medidor'])->toHaveCount(1)
        ->and($datos)->not->toHaveKey('motivo_no_ejecucion')
        ->and($datos)->not->toHaveKey('fotos_no_ejecucion')
        ->and($datos)->not->toHaveKey('firma_usuario')
        ->and($reporte->observaciones)->toBe('Sin novedad')
        ->and($reporte->imagenes)->toBeNull()
        ->and($orden->fresh()->estado)->toBe(EstadoOrden::EnRevision);
});

it('crea un reporte con taponamiento sin requerir series', function () {
    Mail::fake();
    [$user, $orden] = suspensionesContexto();

    crearSuspension($user, $orden, camposEjecutada([
        'metodo_suspension' => 'taponamiento_acometida',
        'fotos_suspension' => fotosSuspensiones('s', 4),
    ]));

    $datos = reportes::where('orden_job_id', $orden->id)->firstOrFail()->datos_servicio;

    expect($datos['metodo_suspension'])->toBe('taponamiento_acometida')
        ->and($datos)->not->toHaveKey('serie_sonda')
        ->and($datos['fotos_suspension'])->toHaveCount(4);
});

it('exige los campos del medidor cuando la suspension se ejecuto', function () {
    Mail::fake();
    [$user, $orden] = suspensionesContexto();

    Livewire::actingAs($user)
        ->test(ReporteCreate::class, ['orden' => $orden])
        ->fillForm([
            'categoria_campo' => 'comercial',
            'suspension_ejecutada' => 1,
            'metodo_suspension' => 'taponamiento_acometida',
            'fotos_fachada' => fotosSuspensiones('f'),
        ])
        ->call('submit')
        ->assertHasFormErrors([
            'lectura_medidor' => 'required',
            'fotos_suspension' => 'required',
            'fotos_medidor' => 'required',
        ]);
});

it('exige motivo, justificacion y fotos cuando no se ejecuto y no guarda campos de la otra rama', function () {
    Mail::fake();
    [$user, $orden] = suspensionesContexto();

    Livewire::actingAs($user)
        ->test(ReporteCreate::class, ['orden' => $orden])
        ->fillForm([
            'categoria_campo' => 'industrial',
            'suspension_ejecutada' => 0,
            'fotos_fachada' => fotosSuspensiones('f'),
        ])
        ->call('submit')
        ->assertHasFormErrors([
            'motivo_no_ejecucion' => 'required',
            'justificacion_no_ejecucion' => 'required',
            'fotos_no_ejecucion' => 'required',
        ]);

    crearSuspension($user, $orden, camposNoEjecutada());

    $datos = reportes::where('orden_job_id', $orden->id)->firstOrFail()->datos_servicio;

    expect($datos['suspension_ejecutada'])->toBeFalse()
        ->and($datos['motivo_no_ejecucion'])->toBe('predio_cerrado')
        ->and($datos['justificacion_no_ejecucion'])->toBe('Nadie atendio')
        ->and($datos['fotos_no_ejecucion'])->toHaveCount(1)
        ->and($datos)->not->toHaveKey('metodo_suspension')
        ->and($datos)->not->toHaveKey('lectura_medidor')
        ->and($datos)->not->toHaveKey('fotos_suspension')
        ->and($datos)->not->toHaveKey('fotos_medidor');
});

it('descarta los valores de la rama no aplicable al cambiar de Si a No', function () {
    Mail::fake();
    [$user, $orden] = suspensionesContexto();

    crearSuspension($user, $orden, [
        ...camposEjecutada(['metodo_suspension' => 'taponamiento_acometida']),
        ...camposNoEjecutada(),
    ]);

    $datos = reportes::where('orden_job_id', $orden->id)->firstOrFail()->datos_servicio;

    expect($datos['suspension_ejecutada'])->toBeFalse()
        ->and($datos)->not->toHaveKey('lectura_medidor')
        ->and($datos)->not->toHaveKey('fotos_medidor')
        ->and($datos)->not->toHaveKey('metodo_suspension')
        ->and($datos)->toHaveKey('motivo_no_ejecucion');
});

it('exige minimo 3 fotos de fachada y de suspension', function () {
    Mail::fake();
    [$user, $orden] = suspensionesContexto();

    Livewire::actingAs($user)
        ->test(ReporteCreate::class, ['orden' => $orden])
        ->fillForm(camposEjecutada([
            'metodo_suspension' => 'taponamiento_multifamiliar',
            'fotos_fachada' => fotosSuspensiones('f', 2),
            'fotos_suspension' => fotosSuspensiones('s', 2),
        ]))
        ->call('submit')
        ->assertHasFormErrors(['fotos_fachada' => 'min', 'fotos_suspension' => 'min']);

    expect(reportes::where('orden_job_id', $orden->id)->exists())->toBeFalse();
});

it('descarta referencias temporales de livewire en todos los grupos de fotos', function () {
    Mail::fake();
    [$user, $orden] = suspensionesContexto();

    crearSuspension($user, $orden, camposEjecutada([
        'metodo_suspension' => 'taponamiento_acometida',
        'fotos_fachada' => [...fotosSuspensiones('f'), 'livewire-file:abc'],
        'fotos_medidor' => [...fotosSuspensiones('m', 1), 'livewire-file:def'],
    ]));

    $datos = reportes::where('orden_job_id', $orden->id)->firstOrFail()->datos_servicio;

    expect($datos['fotos_fachada'])->toBe(fotosSuspensiones('f'))
        ->and($datos['fotos_medidor'])->toBe(fotosSuspensiones('m', 1));
});

it('edita un reporte cargando el estado guardado, cambia de Si a No y limpia la rama anterior', function () {
    Mail::fake();
    [$user, $orden] = suspensionesContexto(EstadoOrden::Rechazada);

    $reporte = reportes::create([
        'orden_job_id' => $orden->id,
        'personals_id' => $user->personals_id,
        'numero_orden' => $orden->numero_orden,
        'requiere_correccion' => true,
        'datos_servicio' => [
            ...camposEjecutada(['suspension_ejecutada' => true, 'metodo_suspension' => 'sonda_cts', 'serie_sonda' => '111', 'serie_caps' => '222']),
        ],
    ]);

    Livewire::actingAs($user)
        ->test(ReporteEdit::class, ['orden' => $orden])
        ->assertSet('data.categoria_campo', 'residencial')
        ->assertSet('data.metodo_suspension', 'sonda_cts')
        ->assertSet('data.serie_sonda', '111')
        ->assertSet('data.lectura_medidor', '123.45')
        ->fillForm([
            'suspension_ejecutada' => 0,
            'motivo_no_ejecucion' => 'usuario_impide',
            'justificacion_no_ejecucion' => 'No dejo pasar',
            'fotos_no_ejecucion' => fotosSuspensiones('n', 2),
            'observaciones' => 'Corregido',
        ])
        ->call('submit')
        ->assertHasNoFormErrors();

    $reporte->refresh();
    $datos = $reporte->datos_servicio;

    expect($reporte->requiere_correccion)->toBeFalse()
        ->and($datos['suspension_ejecutada'])->toBeFalse()
        ->and($datos['motivo_no_ejecucion'])->toBe('usuario_impide')
        ->and($datos['fotos_no_ejecucion'])->toHaveCount(2)
        ->and($datos['fotos_fachada'])->toHaveCount(3)
        ->and($datos)->not->toHaveKey('metodo_suspension')
        ->and($datos)->not->toHaveKey('serie_sonda')
        ->and($datos)->not->toHaveKey('fotos_suspension')
        ->and($datos)->not->toHaveKey('fotos_medidor')
        ->and($reporte->observaciones)->toBe('Corregido')
        ->and($orden->fresh()->estado)->toBe(EstadoOrden::EnRevision);
});

it('muestra la lectura de referencia y guarda aunque la lectura registrada no coincida', function () {
    Mail::fake();
    [$user, $orden] = suspensionesContexto();
    $orden->forceFill(['lectura_referencia' => '987.65'])->save();

    Livewire::actingAs($user)
        ->test(ReporteCreate::class, ['orden' => $orden->fresh()])
        ->fillForm(camposEjecutada(['metodo_suspension' => 'taponamiento_acometida']))
        ->assertSee('Lectura de referencia (proporcionada)')
        ->assertSee('987.65')
        ->assertSee('Debe coincidir con la lectura de referencia')
        ->call('submit')
        ->assertHasNoFormErrors();

    $datos = reportes::where('orden_job_id', $orden->id)->firstOrFail()->datos_servicio;

    expect($datos['lectura_medidor'])->toBe('123.45')
        ->and($datos)->not->toHaveKey('lectura_referencia_display');
});

it('muestra un guion cuando la orden no tiene lectura de referencia', function () {
    [$user, $orden] = suspensionesContexto();

    Livewire::actingAs($user)
        ->test(ReporteCreate::class, ['orden' => $orden])
        ->fillForm(['suspension_ejecutada' => 1])
        ->assertSee('Lectura de referencia (proporcionada)');
});

it('muestra los datos y comentarios de la orden en crear y editar', function () {
    [$user, $orden] = suspensionesContexto(EstadoOrden::Rechazada);
    $orden->forceFill([
        'cliente' => 'Maria Perez',
        'direccion' => 'Calle 10 # 5-20',
        'barrio' => 'Villa Sol',
        'comentario_usuario' => 'Suspender por mora acumulada',
        'comentario_tecnico' => 'Medidor en patio trasero',
    ])->save();

    Livewire::actingAs($user)
        ->test(ReporteCreate::class, ['orden' => $orden->fresh()])
        ->assertSee('Maria Perez')
        ->assertSee('Calle 10 # 5-20')
        ->assertSee('Villa Sol')
        ->assertSee('Suspender por mora acumulada')
        ->assertSee('Medidor en patio trasero');

    $reporte = reportes::create([
        'orden_job_id' => $orden->id,
        'personals_id' => $user->personals_id,
        'numero_orden' => $orden->numero_orden,
        'requiere_correccion' => true,
        'datos_servicio' => ['categoria_campo' => 'residencial', 'suspension_ejecutada' => false],
    ]);

    Livewire::actingAs($user)
        ->test(ReporteEdit::class, ['orden' => $orden->fresh()])
        ->assertSee('Maria Perez')
        ->assertSee('Suspender por mora acumulada')
        ->assertSee('Medidor en patio trasero');
});

it('guarda sin anomalias, sellos ni elementos rotos tanto en Si como en No', function () {
    Mail::fake();
    [$user, $orden] = suspensionesContexto();

    $sinEstado = fn (array $campos): array => collect($campos)
        ->except(['anomalias_medidor', 'condicion_sellos', 'elementos_rotos'])
        ->all();

    crearSuspension($user, $orden, $sinEstado(camposEjecutada(['metodo_suspension' => 'taponamiento_acometida'])));
    [$user2, $orden2] = suspensionesContexto();
    crearSuspension($user2, $orden2, camposNoEjecutada());

    expect(reportes::where('orden_job_id', $orden->id)->exists())->toBeTrue()
        ->and(reportes::where('orden_job_id', $orden2->id)->exists())->toBeTrue();
});

it('conserva anomalias, sellos y elementos rotos cuando no se ejecuto', function () {
    Mail::fake();
    [$user, $orden] = suspensionesContexto();

    $anomalias = [
        TipoAnomalia::create(['nombre' => 'Vidrio roto', 'activo' => true, 'company_id' => $user->company_id])->id,
        TipoAnomalia::create(['nombre' => 'Sello violado', 'activo' => true, 'company_id' => $user->company_id])->id,
    ];

    crearSuspension($user, $orden, camposNoEjecutada([
        'anomalias_medidor' => $anomalias,
        'condicion_sellos' => 'Violados',
        'elementos_rotos' => 'Regulador',
        'lectura_medidor' => '55',
    ]));

    $datos = reportes::where('orden_job_id', $orden->id)->firstOrFail()->datos_servicio;

    expect($datos['anomalias_medidor'])->toBe($anomalias)
        ->and($datos['condicion_sellos'])->toBe('Violados')
        ->and($datos['elementos_rotos'])->toBe('Regulador')
        ->and($datos)->not->toHaveKey('lectura_medidor');
});

it('el select de anomalias solo ofrece las del catalogo activas', function () {
    [$user, $orden] = suspensionesContexto();
    $activa = TipoAnomalia::create(['nombre' => 'Activa X', 'activo' => true, 'company_id' => $user->company_id]);
    $inactiva = TipoAnomalia::create(['nombre' => 'Inactiva X', 'activo' => false, 'company_id' => $user->company_id]);

    Livewire::actingAs($user)
        ->test(ReporteCreate::class, ['orden' => $orden])
        ->assertSee('Activa X')
        ->assertDontSee('Inactiva X');
});

it('edita guardando y cargando el array de anomalias', function () {
    Mail::fake();
    [$user, $orden] = suspensionesContexto(EstadoOrden::Rechazada);
    $a = TipoAnomalia::create(['nombre' => 'Anom A', 'activo' => true, 'company_id' => $user->company_id])->id;
    $b = TipoAnomalia::create(['nombre' => 'Anom B', 'activo' => true, 'company_id' => $user->company_id])->id;

    $reporte = reportes::create([
        'orden_job_id' => $orden->id,
        'personals_id' => $user->personals_id,
        'numero_orden' => $orden->numero_orden,
        'requiere_correccion' => true,
        'datos_servicio' => camposEjecutada(['suspension_ejecutada' => true, 'metodo_suspension' => 'taponamiento_acometida', 'anomalias_medidor' => [$a]]),
    ]);

    Livewire::actingAs($user)
        ->test(ReporteEdit::class, ['orden' => $orden])
        ->assertSet('data.anomalias_medidor', [$a])
        ->fillForm(['anomalias_medidor' => [$a, $b]])
        ->call('submit')
        ->assertHasNoFormErrors();

    expect($reporte->fresh()->datos_servicio['anomalias_medidor'])->toBe([$a, $b]);
});
