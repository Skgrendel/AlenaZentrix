<?php

use App\Models\Company;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;

beforeEach(function () {
    $this->seed(PermissionsSeeder::class);
    $company = Company::factory()->create();
    $this->user = User::factory()->create(['company_id' => $company->id]);
    $this->user->assignRole('Coordinador');
});

it('coordinador puede descargar la plantilla de importación', function () {
    $this->actingAs($this->user)
        ->get(route('operaciones.revision-medidores.plantilla'))
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

it('técnico no puede descargar la plantilla', function () {
    $this->user->syncRoles(['Lector']);

    $this->actingAs($this->user)
        ->get(route('operaciones.revision-medidores.plantilla'))
        ->assertForbidden();
});
