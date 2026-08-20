<?php

use App\Enums\EstadoOrden;
use App\Enums\TipoServicio;
use App\Livewire\Coordinador\ReporteRevisar;
use App\Models\Company;
use App\Models\OrdenJob;
use App\Models\personals;
use App\Models\reportes;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('renderiza Anexos de la Orden sin errores para cada tipo de servicio', function (TipoServicio $tipoServicio) {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $this->actingAs($user);

    $personal = personals::factory()->create(['company_id' => $company->id]);
    $orden = OrdenJob::factory()->create([
        'tipo_servicio' => $tipoServicio->value,
        'company_id' => $company->id,
        'personals_id' => $personal->id,
        'estado' => EstadoOrden::EnRevision->value,
    ]);
    reportes::factory()->create([
        'orden_job_id' => $orden->id,
        'personals_id' => $personal->id,
        'numero_orden' => $orden->numero_orden,
    ]);

    Livewire::test(ReporteRevisar::class, ['orden' => $orden])
        ->assertOk()
        ->assertSee($orden->numero_orden);
})->with([
    TipoServicio::Fraudes,
    TipoServicio::Investigacion,
    TipoServicio::GestionLegal,
    TipoServicio::Vigilancia,
    TipoServicio::AuditoriaCampo,
]);
