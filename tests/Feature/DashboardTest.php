<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('dashboard shows company name when user has a company', function () {
    $company = Company::factory()->create(['nombre' => 'Gases del Caribe']);
    $user = User::factory()->create(['company_id' => $company->id]);
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertSee('Gases del Caribe');
});

test('dashboard shows company logo img when company has logo', function () {
    $company = Company::factory()->create([
        'nombre' => 'Gases del Caribe',
        'logo'   => 'https://example.com/logo.svg',
    ]);
    $user = User::factory()->create(['company_id' => $company->id]);
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertSee('https://example.com/logo.svg');
});

test('dashboard does not show company section for superadmin without company', function () {
    $user = User::factory()->superadmin()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
    $response->assertDontSee('Gases del Caribe');
});
