<?php

use App\Enums\EstadoOrden;
use App\Enums\TipoServicio;
use App\Livewire\Coordinador\OrdenCreate;
use App\Livewire\Tecnico\ReporteCreate;
use App\Livewire\Tecnico\ReporteEdit;
use App\Models\Company;
use App\Models\OrdenJob;
use App\Models\personals;
use App\Models\reportes;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PermissionsSeeder::class);
    $this->company = Company::factory()->create();
});

it('técnico puede ver el formulario de reporte para una orden RevisionMedidores', function () {
    $personal = personals::factory()->create(['company_id' => $this->company->id]);
    $user = User::factory()->create(['personals_id' => $personal->id]);
    $user->assignRole('Lector');

    $orden = OrdenJob::factory()->create([
        'tipo_servicio' => TipoServicio::RevisionMedidores->value,
        'estado' => EstadoOrden::Asignada->value,
        'personals_id' => $personal->id,
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($user);

    Livewire::test(ReporteCreate::class, ['orden' => $orden])
        ->assertOk();
});

it('técnico puede ver el formulario de edición de reporte para una orden RevisionMedidores', function () {
    $personal = personals::factory()->create(['company_id' => $this->company->id]);
    $user = User::factory()->create(['personals_id' => $personal->id]);
    $user->assignRole('Lector');

    $orden = OrdenJob::factory()->create([
        'tipo_servicio' => TipoServicio::RevisionMedidores->value,
        'estado' => EstadoOrden::Rechazada->value,
        'personals_id' => $personal->id,
        'company_id' => $this->company->id,
    ]);

    $reporte = reportes::factory()->create(['orden_job_id' => $orden->id]);

    $this->actingAs($user);

    Livewire::test(ReporteEdit::class, ['orden' => $orden])
        ->assertOk();
});

it('RevisionMedidores no aparece en el dropdown de OrdenCreate', function () {
    $user = User::factory()->create();
    $user->assignRole('Coordinador');

    $this->actingAs($user);

    Livewire::test(OrdenCreate::class)
        ->assertDontSee('Revisión de Medidores');
});
