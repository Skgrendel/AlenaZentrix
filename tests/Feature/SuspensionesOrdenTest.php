<?php

use App\Enums\TipoServicio;
use App\Livewire\Coordinador\OrdenCreate;
use App\Livewire\Informes\InformesIndex;
use App\Models\OrdenJob;
use App\Models\reportes;
use App\Models\TipoAnomalia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('OrdenJob devuelve la etiqueta del subtipo de suspensiones', function () {
    $orden = OrdenJob::factory()->create([
        'tipo_servicio' => TipoServicio::AuditoriaCampo,
        'tipo_auditoria_campo' => 'visita_calidad_suspensiones',
    ]);

    expect($orden->subtipoLabel())->toBe('Visita de Calidad — Suspensiones');
});

test('el informe de suspensiones muestra el subtipo y sus columnas', function () {
    $user = User::factory()->create();
    $bypass = TipoAnomalia::create(['nombre' => 'Bypass', 'nomenclatura' => 'BYP', 'activo' => true, 'company_id' => $user->company_id]);
    $sellos = TipoAnomalia::create(['nombre' => 'Sellos rotos', 'nomenclatura' => 'SR', 'activo' => true, 'company_id' => $user->company_id]);

    $orden = OrdenJob::factory()->create([
        'tipo_servicio' => TipoServicio::AuditoriaCampo,
        'tipo_auditoria_campo' => 'visita_calidad_suspensiones',
        'numero_orden' => 'ODT-SUS-001',
        'lectura_referencia' => '123',
    ]);

    reportes::create([
        'orden_job_id' => $orden->id,
        'personals_id' => null,
        'numero_orden' => $orden->numero_orden,
        'datos_servicio' => [
            'categoria_campo' => 'residencial',
            'suspension_ejecutada' => false,
            'metodo_suspension' => 'sonda_ips',
            'serie_sonda' => 'SN-1',
            'serie_caps' => 'CP-2',
            'motivo_no_ejecucion' => 'predio_cerrado',
            'fotos_fachada' => ['a.jpg', 'b.jpg'],
            'fotos_suspension' => ['c.jpg'],
            'lectura_medidor' => '123',
            'anomalias_medidor' => [$bypass->id, $sellos->id],
            'condicion_sellos' => 'Intactos',
            'elementos_rotos' => 'Ninguno',
            'justificacion_no_ejecucion' => 'Predio cerrado',
            'fotos_medidor' => ['m1.jpg', 'm2.jpg', 'm3.jpg'],
            'fotos_no_ejecucion' => ['n.jpg'],
        ],
    ]);

    $preview = Livewire::actingAs($user)
        ->test(InformesIndex::class)
        ->set('servicio', TipoServicio::AuditoriaCampo->value)
        ->set('subTipo', 'visita_calidad_suspensiones')
        ->call('generarVista')
        ->get('preview');

    $fila = collect($preview)->firstWhere('N° Orden', 'ODT-SUS-001');

    expect($fila['Sub-tipo'])->toBe('Visita de Calidad — Suspensiones')
        ->and($fila['Categoría en campo'])->toBe('Residencial')
        ->and($fila['¿Suspensión ejecutada?'])->toBe('No')
        ->and($fila['Método instalado'])->toBe('Sonda (Dispositivo IPS)')
        ->and($fila['Serie sonda'])->toBe('SN-1')
        ->and($fila['Serie Caps'])->toBe('CP-2')
        ->and($fila['Motivo no ejecución'])->toBe('Predio cerrado o desocupado')
        ->and($fila['N° fotos fachada'])->toBe('2')
        ->and($fila['N° fotos suspensión'])->toBe('1')
        ->and($fila['Lectura medidor'])->toBe('123')
        ->and($fila['Lectura referencia'])->toBe('123')
        ->and($fila['Coincide lectura'])->toBe('Sí')
        ->and($fila['Anomalías medidor'])->toBe('Bypass, Sellos rotos')
        ->and($fila['Condición sellos'])->toBe('Intactos')
        ->and($fila['Elementos rotos'])->toBe('Ninguno')
        ->and($fila['Justificación no ejecución'])->toBe('Predio cerrado')
        ->and($fila['N° fotos medidor'])->toBe('3')
        ->and($fila['N° fotos no ejecución'])->toBe('1');
});

test('lecturaCoincide compara lecturas normalizando coma decimal y espacios', function () {
    $orden = OrdenJob::factory()->make(['lectura_referencia' => '1234.5']);

    expect($orden->lecturaCoincide('1234,5'))->toBeTrue()
        ->and($orden->lecturaCoincide(' 1234.50 '))->toBeTrue()
        ->and($orden->lecturaCoincide(1234.5))->toBeTrue()
        ->and($orden->lecturaCoincide('1235'))->toBeFalse()
        ->and($orden->lecturaCoincide(''))->toBeNull()
        ->and($orden->lecturaCoincide(null))->toBeNull()
        ->and($orden->lecturaCoincide('abc'))->toBeNull();

    $sinReferencia = OrdenJob::factory()->make(['lectura_referencia' => null]);
    expect($sinReferencia->lecturaCoincide('10'))->toBeNull();

    $entera = OrdenJob::factory()->make(['lectura_referencia' => '1234']);
    expect($entera->lecturaCoincide('1234.0'))->toBeTrue();
});

test('la lectura de referencia es obligatoria al crear una orden de suspensiones', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(OrdenCreate::class)
        ->fillForm([
            'tipo_servicio' => TipoServicio::AuditoriaCampo->value,
            'tipo_auditoria_campo' => 'visita_calidad_suspensiones',
        ])
        ->call('guardar')
        ->assertHasFormErrors(['lectura_referencia' => 'required']);
});
