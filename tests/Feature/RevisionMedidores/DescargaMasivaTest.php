<?php

use App\Enums\EstadoOrden;
use App\Enums\TipoServicio;
use App\Jobs\GenerarZipReportesRevisionMedidoresJob;
use App\Livewire\Coordinador\RevisionMedidores\RevisionMedidoresIndex;
use App\Models\Company;
use App\Models\OrdenJob;
use App\Models\personals;
use App\Models\User;
use App\Notifications\ZipReportesListoNotification;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionsSeeder::class);
    $this->company = Company::factory()->create();
    $this->personal = personals::factory()->create(['company_id' => $this->company->id]);
    $this->user = User::factory()->create([
        'personals_id' => $this->personal->id,
        'company_id' => $this->company->id,
    ]);
    $this->user->assignRole('Coordinador');
});

it('la acción masiva despacha el job solo con órdenes completadas', function () {
    Queue::fake();
    $this->actingAs($this->user);

    $completadas = OrdenJob::factory()->count(2)->create([
        'tipo_servicio' => TipoServicio::RevisionMedidores->value,
        'company_id' => $this->company->id,
        'estado' => EstadoOrden::Completada->value,
        'personals_id' => $this->personal->id,
    ]);

    $completadasIds = $completadas->pluck('id')->all();

    $pendiente = OrdenJob::factory()->create([
        'tipo_servicio' => TipoServicio::RevisionMedidores->value,
        'company_id' => $this->company->id,
        'estado' => EstadoOrden::Pendiente->value,
        'personals_id' => null,
    ]);

    $todas = $completadas->push($pendiente);

    Livewire::test(RevisionMedidoresIndex::class)
        ->callTableBulkAction('descargar_pdfs', $todas, data: []);

    Queue::assertPushed(GenerarZipReportesRevisionMedidoresJob::class, function ($job) use ($completadasIds) {
        return count($job->ordenIds) === 2
            && empty(array_diff($completadasIds, $job->ordenIds));
    });
});

it('la acción masiva no despacha el job si ninguna orden está completada', function () {
    Queue::fake();
    $this->actingAs($this->user);

    $ordenes = OrdenJob::factory()->count(3)->create([
        'tipo_servicio' => TipoServicio::RevisionMedidores->value,
        'company_id' => $this->company->id,
        'estado' => EstadoOrden::Asignada->value,
        'personals_id' => $this->personal->id,
    ]);

    Livewire::test(RevisionMedidoresIndex::class)
        ->callTableBulkAction('descargar_pdfs', $ordenes);

    Queue::assertNotPushed(GenerarZipReportesRevisionMedidoresJob::class);
});

it('checkZipNotificacion marca la notificación como leída y activa el banner', function () {
    $this->actingAs($this->user);

    $this->user->notify(new ZipReportesListoNotification('https://example.com/zip', 3));

    expect($this->user->unreadNotifications()->count())->toBe(1);

    $component = Livewire::test(RevisionMedidoresIndex::class)
        ->call('checkZipNotificacion');

    $component->assertSet('zipListo', true);
    $component->assertSet('zipUrl', 'https://example.com/zip');

    expect($this->user->fresh()->unreadNotifications()->count())->toBe(0);
});
