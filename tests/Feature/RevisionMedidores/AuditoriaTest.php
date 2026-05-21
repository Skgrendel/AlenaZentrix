<?php

use App\Enums\TipoServicio;
use App\Livewire\Auditor\AuditoriasIndex;
use App\Models\auditoria;
use App\Models\Company;
use App\Models\OrdenJob;
use App\Models\personals;
use App\Models\reportes;
use App\Models\User;
use App\Models\UsuarioServicioAuditable;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionsSeeder::class);
    $this->company = Company::factory()->create();
});

it('revision_medidores es un tipo de servicio auditable válido', function () {
    $user = User::factory()->create(['company_id' => $this->company->id]);

    UsuarioServicioAuditable::create([
        'user_id' => $user->id,
        'tipo_servicio' => TipoServicio::RevisionMedidores->value,
    ]);

    expect($user->serviciosAuditables()->pluck('tipo_servicio'))
        ->toContain(TipoServicio::RevisionMedidores->value);
});

it('auditor con revision_medidores asignado ve auditorías de ese tipo', function () {
    $auditor = User::factory()->create(['company_id' => $this->company->id]);
    $auditor->assignRole('Auditor');

    UsuarioServicioAuditable::create([
        'user_id' => $auditor->id,
        'tipo_servicio' => TipoServicio::RevisionMedidores->value,
    ]);

    $personal = personals::factory()->create(['company_id' => $this->company->id]);
    $orden = OrdenJob::factory()->create([
        'tipo_servicio' => TipoServicio::RevisionMedidores->value,
        'company_id' => $this->company->id,
        'personals_id' => $personal->id,
    ]);
    $reporte = reportes::factory()->create([
        'orden_job_id' => $orden->id,
        'personals_id' => $personal->id,
        'numero_orden' => $orden->numero_orden,
    ]);
    $auditoriaRecord = auditoria::create([
        'reportes_id' => $reporte->id,
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($auditor);

    Livewire::test(AuditoriasIndex::class)
        ->assertSee($orden->numero_orden);
});

it('auditor sin revision_medidores no ve auditorías de ese tipo', function () {
    $auditor = User::factory()->create(['company_id' => $this->company->id]);
    $auditor->assignRole('Auditor');

    $personal = personals::factory()->create(['company_id' => $this->company->id]);
    $orden = OrdenJob::factory()->create([
        'tipo_servicio' => TipoServicio::RevisionMedidores->value,
        'company_id' => $this->company->id,
        'personals_id' => $personal->id,
    ]);
    $reporte = reportes::factory()->create([
        'orden_job_id' => $orden->id,
        'personals_id' => $personal->id,
        'numero_orden' => $orden->numero_orden,
    ]);
    auditoria::create([
        'reportes_id' => $reporte->id,
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($auditor);

    Livewire::test(AuditoriasIndex::class)
        ->assertDontSee($orden->numero_orden);
});
