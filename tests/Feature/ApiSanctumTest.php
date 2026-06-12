<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('GET /api/tecnico/mis-ordenes requiere autenticación', function () {
    $this->getJson('/api/tecnico/mis-ordenes')->assertStatus(401);
});

test('GET /api/tecnico/mis-ordenes responde con token Sanctum válido', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test-token')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/tecnico/mis-ordenes')
        ->assertOk()
        ->assertJsonStructure(['data']);
});
