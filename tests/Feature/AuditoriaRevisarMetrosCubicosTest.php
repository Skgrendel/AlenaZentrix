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

function auditoriaConfirmadaSinMetrosCubicos(): array
{
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
    ]);

    $auditoriaRecord = auditoria::create([
        'reportes_id' => $reporte->id,
        'estado' => EstadoAuditoria::Confirmado->value,
        'evidencias' => [],
        'fecha_auditoria' => now(),
        'metros_cubicos_recuperados' => null,
    ]);

    return [$tecnico, $auditoriaRecord];
}

it('muestra el botón para agregar M³ recuperados cuando el dato está vacío', function () {
    [$tecnico, $auditoriaRecord] = auditoriaConfirmadaSinMetrosCubicos();
    $this->actingAs($tecnico);

    Livewire::test(AuditoriaRevisar::class, ['auditoria' => $auditoriaRecord])
        ->assertSee('Agregar M³ Recuperados');
});

it('valida que el valor sea requerido al guardar los M³ recuperados', function () {
    [$tecnico, $auditoriaRecord] = auditoriaConfirmadaSinMetrosCubicos();
    $this->actingAs($tecnico);

    Livewire::test(AuditoriaRevisar::class, ['auditoria' => $auditoriaRecord])
        ->set('metrosCubicosRecuperados', null)
        ->call('guardarMetrosCubicosRecuperados')
        ->assertHasErrors(['metrosCubicosRecuperados' => 'required']);

    expect($auditoriaRecord->fresh()->metros_cubicos_recuperados)->toBeNull();
});

it('permite completar los M³ recuperados después de registrada la auditoría', function () {
    [$tecnico, $auditoriaRecord] = auditoriaConfirmadaSinMetrosCubicos();
    $this->actingAs($tecnico);

    Livewire::test(AuditoriaRevisar::class, ['auditoria' => $auditoriaRecord])
        ->set('metrosCubicosRecuperados', 50)
        ->call('guardarMetrosCubicosRecuperados')
        ->assertHasNoErrors()
        ->assertDontSee('Agregar M³ Recuperados');

    expect($auditoriaRecord->fresh()->metros_cubicos_recuperados)->toBe('50');
});
