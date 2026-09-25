<?php

use App\Enums\EstadoAuditoria;
use App\Enums\EstadoOrden;
use App\Enums\TipoServicio;
use App\Livewire\Auditor\AuditoriaRevisar;
use App\Models\auditoria;
use App\Models\OrdenJob;
use App\Models\reportes;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('excluye referencias temporales de livewire de las fotos del reporte auditado', function () {
    $tecnico = User::factory()->create();

    $orden = OrdenJob::factory()->create([
        'company_id' => $tecnico->company_id,
        'personals_id' => $tecnico->personals_id,
        'tipo_servicio' => TipoServicio::Fraudes->value,
        'estado' => EstadoOrden::Completada->value,
    ]);

    $reporte = reportes::factory()->create([
        'orden_job_id' => $orden->id,
        'personals_id' => $tecnico->personals_id,
        'numero_orden' => $orden->numero_orden,
        'company_id' => $tecnico->company_id,
        'imagenes' => ['reportes/foto-real.jpg', 'livewire-file:cBgjMvKLVl6iY4oNK3HlZ5AM6JilPLBuTibZ'],
    ]);

    $auditoriaRecord = auditoria::create([
        'reportes_id' => $reporte->id,
        'estado' => EstadoAuditoria::Confirmado->value,
        'evidencias' => [],
        'fecha_auditoria' => now(),
    ]);

    $this->actingAs($tecnico);

    Livewire::test(AuditoriaRevisar::class, ['auditoria' => $auditoriaRecord])
        ->assertDontSee('livewire-file:');
});
