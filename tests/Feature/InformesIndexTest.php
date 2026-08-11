<?php

use App\Livewire\Informes\InformesIndex;
use App\Models\OrdenJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('la vista previa incluye la columna Resultado con las etiquetas correctas', function () {
    $user = User::factory()->create();

    OrdenJob::factory()->create(['resultado' => 'positivo']);
    OrdenJob::factory()->create(['resultado' => 'negativo']);
    OrdenJob::factory()->create(['resultado' => 'pendiente']);

    $preview = Livewire::actingAs($user)
        ->test(InformesIndex::class)
        ->call('generarVista')
        ->get('preview');

    $resultados = collect($preview)->pluck('Resultado')->all();

    expect($resultados)->toContain('Positiva', 'Negativa', '—');
});
