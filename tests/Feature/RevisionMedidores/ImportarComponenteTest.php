<?php

use App\Enums\TipoServicio;
use App\Livewire\Coordinador\RevisionMedidores\ImportarRevisionMedidores;
use App\Models\Company;
use App\Models\OrdenJob;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionsSeeder::class);
    $this->company = Company::factory()->create();
    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    $this->user->assignRole('Coordinador');
});

it('el componente renderiza correctamente', function () {
    $this->actingAs($this->user);

    Livewire::test(ImportarRevisionMedidores::class)
        ->assertOk()
        ->assertSee('Importar Órdenes')
        ->assertSee('Descargar Plantilla');
});

it('técnico no puede acceder al componente', function () {
    $this->user->syncRoles(['Lector']);
    $this->actingAs($this->user);

    $this->get(route('operaciones.revision-medidores.importar'))
        ->assertForbidden();
});

it('importa un archivo CSV válido y crea las órdenes', function () {
    Storage::fake('local');
    $this->actingAs($this->user);

    $csv = "contrato,cliente,direccion,barrio,medidor,tipo_medidor,ciclo,descripcion\n"
         ."CONT-A,Cliente A,Calle 1,Centro,MED-A,digital,C1,Verificar\n"
         ."CONT-B,Cliente B,Calle 2,Norte,MED-B,,,\n";

    $path = 'temp/imports/ordenes.csv';
    Storage::disk('local')->put($path, $csv);

    // FileUpload stores state as an associative array (key => path)
    Livewire::test(ImportarRevisionMedidores::class)
        ->set('data.archivo', ['file' => $path])
        ->call('importar')
        ->assertHasNoErrors()
        ->assertSee('2 órdenes importadas')
        ->assertSee('0 errores');

    expect(OrdenJob::where('tipo_servicio', TipoServicio::RevisionMedidores->value)->count())->toBe(2);
});

it('muestra errores de filas inválidas y sigue importando las válidas', function () {
    Storage::fake('local');
    $this->actingAs($this->user);

    $csv = "contrato,cliente,direccion,barrio,medidor,tipo_medidor,ciclo,descripcion\n"
         ."CONT-OK,Cliente OK,Calle 1,Centro,MED-OK,,,\n"
         .",Sin Contrato,Calle 2,Norte,MED-X,,,\n";

    $path = 'temp/imports/ordenes.csv';
    Storage::disk('local')->put($path, $csv);

    Livewire::test(ImportarRevisionMedidores::class)
        ->set('data.archivo', ['file' => $path])
        ->call('importar')
        ->assertSee('1 órdenes importadas')
        ->assertSee('1 errores');

    expect(OrdenJob::where('tipo_servicio', TipoServicio::RevisionMedidores->value)->count())->toBe(1);
});

it('muestra error de validación si no se sube ningún archivo', function () {
    $this->actingAs($this->user);

    Livewire::test(ImportarRevisionMedidores::class)
        ->call('importar')
        ->assertHasFormErrors(['archivo']);
});
