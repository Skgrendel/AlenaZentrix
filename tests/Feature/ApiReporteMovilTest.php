<?php

use App\Enums\EstadoOrden;
use App\Enums\TipoServicio;
use App\Models\comercio;
use App\Models\OrdenJob;
use App\Models\reportes;
use App\Models\TipoComercio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('API devuelve el id del tipo de comercio y los ids de catalogo del reporte de fraudes', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $tipoComercio = TipoComercio::create(['nombre' => 'Panaderia', 'company_id' => $user->company_id]);
    $comercio = comercio::create(['nombre_comercio' => 'Pan Caliente', 'tipo_comercio_id' => $tipoComercio->id]);

    $orden = OrdenJob::factory()->create([
        'company_id' => $user->company_id,
        'personals_id' => $user->personals_id,
        'tipo_servicio' => TipoServicio::Fraudes->value,
        'estado' => EstadoOrden::Rechazada->value,
    ]);

    reportes::create([
        'orden_job_id' => $orden->id,
        'numero_orden' => $orden->numero_orden,
        'company_id' => $user->company_id,
        'comercios_id' => $comercio->id,
    ]);

    $this->withToken($token)
        ->getJson("/api/tecnico/ordenes/{$orden->id}/reporte")
        ->assertOk()
        ->assertJsonPath('data.comercio.nombre_comercio', 'Pan Caliente')
        ->assertJsonPath('data.comercio.tipo_comercio_id', $tipoComercio->id)
        ->assertJsonPath('data.comercio.tipo_comercio', 'Panaderia')
        ->assertJsonStructure(['data' => [
            'marca_regulador_id',
            'tipo_regulador_id',
            'marca_medidor_id',
            'tipo_notificacion_alerta_id',
            'tipo_imposibilidad_id',
        ]]);
});
