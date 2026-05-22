<?php

use App\Enums\TipoServicio;
use App\Mail\OrdenesAsignadasResumen;
use App\Models\Company;
use App\Models\OrdenJob;
use App\Models\personals;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;

beforeEach(function () {
    $this->seed(PermissionsSeeder::class);
    $company = Company::factory()->create();
    $this->personal = personals::factory()->create(['company_id' => $company->id]);
    User::factory()->create(['personals_id' => $this->personal->id, 'company_id' => $company->id]);
    $this->ordenes = OrdenJob::factory()->count(3)->create([
        'tipo_servicio' => TipoServicio::RevisionMedidores->value,
        'personals_id' => $this->personal->id,
        'company_id' => $company->id,
    ]);
});

it('el mailable tiene el subject correcto', function () {
    $mail = new OrdenesAsignadasResumen($this->personal, $this->ordenes);

    expect($mail->envelope()->subject)->toContain('3 Órdenes Asignadas');
});

it('el mailable incluye el nombre del investigador en la vista', function () {
    $mail = new OrdenesAsignadasResumen($this->personal, $this->ordenes);
    $rendered = $mail->render();

    expect($rendered)->toContain($this->personal->nombres);
});

it('el mailable lista los contratos de las órdenes', function () {
    $mail = new OrdenesAsignadasResumen($this->personal, $this->ordenes);
    $rendered = $mail->render();

    foreach ($this->ordenes as $orden) {
        expect($rendered)->toContain($orden->contrato);
    }
});
