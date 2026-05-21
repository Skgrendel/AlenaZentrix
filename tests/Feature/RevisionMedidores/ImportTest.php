<?php

use App\Enums\EstadoOrden;
use App\Enums\TipoServicio;
use App\Imports\RevisionMedidoresImport;
use App\Models\Company;
use App\Models\OrdenJob;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionsSeeder::class);
    $this->company = Company::factory()->create();
    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    $this->user->assignRole('Coordinador');
});

it('importa filas válidas como OrdenJob con tipo RevisionMedidores', function () {
    $this->actingAs($this->user);

    $import = new RevisionMedidoresImport($this->company->id, $this->user->id);

    $rows = collect([
        [
            'contrato' => 'CONT-001',
            'cliente' => 'Cliente Uno',
            'direccion' => 'Calle 1',
            'barrio' => 'Centro',
            'medidor' => 'MED-111',
            'tipo_medidor' => 'digital',
            'ciclo' => 'C01',
            'descripcion' => 'Verificación',
        ],
        [
            'contrato' => 'CONT-002',
            'cliente' => 'Cliente Dos',
            'direccion' => 'Calle 2',
            'barrio' => 'Norte',
            'medidor' => 'MED-222',
            'tipo_medidor' => null,
            'ciclo' => null,
            'descripcion' => null,
        ],
    ]);

    $import->collection($rows);

    expect(OrdenJob::where('tipo_servicio', TipoServicio::RevisionMedidores->value)->count())->toBe(2);

    $orden = OrdenJob::where('contrato', 'CONT-001')->first();
    expect($orden->cliente)->toBe('Cliente Uno')
        ->and($orden->estado->value)->toBe(EstadoOrden::Pendiente->value)
        ->and($orden->company_id)->toBe($this->company->id)
        ->and($orden->creado_por_id)->toBe($this->user->id)
        ->and($orden->comentario_usuario)->toBe('Verificación');
});

it('acumula errores para filas con campos requeridos vacíos', function () {
    $this->actingAs($this->user);

    $import = new RevisionMedidoresImport($this->company->id, $this->user->id);

    $rows = collect([
        [
            'contrato' => '',
            'cliente' => 'Cliente Sin Contrato',
            'direccion' => 'Calle 3',
            'barrio' => 'Sur',
            'medidor' => 'MED-333',
            'tipo_medidor' => null,
            'ciclo' => null,
            'descripcion' => null,
        ],
        [
            'contrato' => 'CONT-004',
            'cliente' => '',
            'direccion' => 'Calle 4',
            'barrio' => 'Este',
            'medidor' => 'MED-444',
            'tipo_medidor' => null,
            'ciclo' => null,
            'descripcion' => null,
        ],
    ]);

    $import->collection($rows);

    expect(OrdenJob::where('tipo_servicio', TipoServicio::RevisionMedidores->value)->count())->toBe(0);
    expect($import->errores())->toHaveCount(2);
    expect($import->errores()[0]['fila'])->toBe(2);
    expect($import->errores()[1]['fila'])->toBe(3);
});

it('importa filas válidas e ignora las inválidas', function () {
    $this->actingAs($this->user);

    $import = new RevisionMedidoresImport($this->company->id, $this->user->id);

    $rows = collect([
        [
            'contrato' => 'CONT-VALIDO',
            'cliente' => 'Cliente Válido',
            'direccion' => 'Calle 5',
            'barrio' => 'Oeste',
            'medidor' => 'MED-555',
            'tipo_medidor' => null,
            'ciclo' => null,
            'descripcion' => null,
        ],
        [
            'contrato' => '',       // inválido
            'cliente' => 'X',
            'direccion' => 'Y',
            'barrio' => 'Z',
            'medidor' => 'W',
            'tipo_medidor' => null,
            'ciclo' => null,
            'descripcion' => null,
        ],
    ]);

    $import->collection($rows);

    expect(OrdenJob::where('tipo_servicio', TipoServicio::RevisionMedidores->value)->count())->toBe(1);
    expect($import->errores())->toHaveCount(1);
});
