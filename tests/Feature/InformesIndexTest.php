<?php

use App\Enums\TipoServicio;
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

test('el filtro por subtipo restringe la vista previa a auditorías de campo del subtipo elegido', function () {
    $user = User::factory()->create();

    OrdenJob::factory()->create([
        'tipo_servicio' => TipoServicio::AuditoriaCampo,
        'tipo_auditoria_campo' => 'visita_calidad_medidores_digitales',
        'numero_orden' => 'ODT-MED-001',
    ]);
    OrdenJob::factory()->create([
        'tipo_servicio' => TipoServicio::AuditoriaCampo,
        'tipo_auditoria_campo' => 'visita_calidad_emergencia',
        'numero_orden' => 'ODT-EMG-001',
    ]);

    $preview = Livewire::actingAs($user)
        ->test(InformesIndex::class)
        ->set('servicio', TipoServicio::AuditoriaCampo->value)
        ->set('subTipo', 'visita_calidad_medidores_digitales')
        ->call('generarVista')
        ->get('preview');

    $numeros = collect($preview)->pluck('N° Orden')->all();

    expect($numeros)->toContain('ODT-MED-001')
        ->and($numeros)->not->toContain('ODT-EMG-001');
});
