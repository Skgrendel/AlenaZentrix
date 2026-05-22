<?php

use App\Models\Company;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionsSeeder::class);
    $this->company = Company::factory()->create();
    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    $this->user->assignRole('Coordinador');
});

it('una URL firmada válida descarga el archivo ZIP', function () {
    $this->actingAs($this->user);

    $zipPath = 'zips/test-'.uniqid().'.zip';
    $absolutePath = storage_path('app/private/'.$zipPath);

    $dir = dirname($absolutePath);
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents($absolutePath, 'fake zip content');

    $signedUrl = URL::temporarySignedRoute(
        'operaciones.revision-medidores.descargar-zip',
        now()->addMinutes(30),
        ['path' => $zipPath]
    );

    $this->get($signedUrl)->assertOk();
});

it('una URL sin firma válida devuelve 403', function () {
    $this->actingAs($this->user);

    $url = route('operaciones.revision-medidores.descargar-zip', ['path' => 'zips/fake.zip']);

    $this->get($url)->assertForbidden();
});

it('devuelve 404 si el archivo no existe', function () {
    $this->actingAs($this->user);

    $signedUrl = URL::temporarySignedRoute(
        'operaciones.revision-medidores.descargar-zip',
        now()->addMinutes(30),
        ['path' => 'zips/does-not-exist.zip']
    );

    $this->get($signedUrl)->assertNotFound();
});
