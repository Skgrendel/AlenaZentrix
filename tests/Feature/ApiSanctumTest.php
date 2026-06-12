<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('GET /api/user requiere autenticación', function () {
    $response = $this->getJson('/api/user');

    $response->assertStatus(401);
});

test('GET /api/user devuelve el usuario autenticado con token Sanctum', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test-token')->plainTextToken;

    $response = $this->withToken($token)->getJson('/api/user');

    $response->assertOk()
        ->assertJsonPath('id', $user->id);
});
