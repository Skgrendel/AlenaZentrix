<?php

use App\Livewire\Coordinador\ConsultaGis\ConsultaGisIndex;
use App\Services\coordinador\DataGisServices;
use Livewire\Livewire;

function resultadoGis(string $contrato, string $direccion): array
{
    return [
        'titular' => ['nombre' => 'CLIENTE '.$contrato, 'zona' => null, 'nup' => '1', 'contrato' => $contrato, 'orden' => '10'],
        'ubicacion' => ['municipio' => 'CARTAGENA', 'direccion' => $direccion, 'barrio' => 'LA BOQUILLA', 'tag' => '1'],
        'servicio' => ['estado' => 'Activo', 'estado_corte' => 'CONEXION', 'descripcion' => 'X', 'categoria' => 'COMERCIAL', 'estrato' => 1, 'ciclo' => 1003, 'anillado' => 'Y'],
        'medidor' => ['serie_actual' => 'S1', 'serie_anterior' => null, 'fecha_anterior' => null],
        'consumos' => ['mes1' => 1, 'mes2' => 2, 'mes3' => 3, 'mes4' => 4, 'mes5' => 5, 'mes6' => 6],
        'coordenadas' => ['lat' => 10.0, 'lng' => -75.0, 'maps_url' => 'https://maps.test'],
    ];
}

test('buscar otro contrato muestra los datos completos del nuevo contrato', function () {
    $servicio = Mockery::mock(DataGisServices::class);
    $servicio->shouldReceive('consultarContrato')->with('111')->andReturn(resultadoGis('111', 'DIRECCION UNO'));
    $servicio->shouldReceive('consultarContrato')->with('222')->andReturn(resultadoGis('222', 'DIRECCION DOS'));
    app()->instance(DataGisServices::class, $servicio);

    Livewire::test(ConsultaGisIndex::class)
        ->set('data.contrato', '111')
        ->call('buscar')
        ->assertSee('DIRECCION UNO')
        ->set('data.contrato', '222')
        ->call('buscar')
        ->assertSee('DIRECCION DOS')
        ->assertDontSee('DIRECCION UNO');
});
