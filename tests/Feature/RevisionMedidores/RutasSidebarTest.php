<?php

use App\Models\Company;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionsSeeder::class);
    $this->company = Company::factory()->create();
});

it('invitados son redirigidos al login al acceder al índice', function () {
    $this->get(route('operaciones.revision-medidores.index'))
        ->assertRedirect(route('login'));
});

it('coordinador puede acceder al índice', function () {
    $user = User::factory()->create(['company_id' => $this->company->id]);
    $user->assignRole('Coordinador');

    $this->actingAs($user)
        ->get(route('operaciones.revision-medidores.index'))
        ->assertOk();
});

it('coordinador puede acceder a importar', function () {
    $user = User::factory()->create(['company_id' => $this->company->id]);
    $user->assignRole('Coordinador');

    $this->actingAs($user)
        ->get(route('operaciones.revision-medidores.importar'))
        ->assertOk();
});

it('lector no puede acceder al índice', function () {
    $user = User::factory()->create(['company_id' => $this->company->id]);
    $user->assignRole('Lector');

    $this->actingAs($user)
        ->get(route('operaciones.revision-medidores.index'))
        ->assertForbidden();
});

it('lector no puede acceder a importar', function () {
    $user = User::factory()->create(['company_id' => $this->company->id]);
    $user->assignRole('Lector');

    $this->actingAs($user)
        ->get(route('operaciones.revision-medidores.importar'))
        ->assertForbidden();
});

it('el sidebar muestra Revisión de Medidores para coordinador', function () {
    $user = User::factory()->create(['company_id' => $this->company->id]);
    $user->assignRole('Coordinador');

    $this->actingAs($user)
        ->get(route('operaciones.revision-medidores.index'))
        ->assertSee('Revisión de Medidores');
});
