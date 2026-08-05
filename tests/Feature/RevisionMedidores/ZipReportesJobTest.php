<?php

use App\Enums\EstadoOrden;
use App\Enums\TipoServicio;
use App\Jobs\GenerarZipReportesRevisionMedidoresJob;
use App\Models\Company;
use App\Models\OrdenJob;
use App\Models\personals;
use App\Models\User;
use App\Notifications\ZipReportesListoNotification;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

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

it('el job se encola con los ids de las órdenes correctas', function () {
    Queue::fake();

    $ordenes = OrdenJob::factory()->count(3)->create([
        'tipo_servicio' => TipoServicio::RevisionMedidores->value,
        'company_id' => $this->company->id,
        'estado' => EstadoOrden::Completada->value,
        'personals_id' => $this->personal->id,
    ]);

    $ids = $ordenes->pluck('id')->all();

    GenerarZipReportesRevisionMedidoresJob::dispatch($ids, $this->user->id);

    Queue::assertPushed(GenerarZipReportesRevisionMedidoresJob::class, function ($job) use ($ids) {
        return count($job->ordenIds) === 3
            && empty(array_diff($ids, $job->ordenIds));
    });
});

it('la notificación usa los canales database y mail', function () {
    $notification = new ZipReportesListoNotification('https://example.com/zip', 3);

    expect($notification->via(new stdClass))->toBe(['database', 'mail']);
});

it('la notificación tiene el asunto correcto en el correo', function () {
    $notification = new ZipReportesListoNotification('https://example.com/zip', 5);
    $mail = $notification->toMail($this->user);

    expect($mail->subject)->toBe('ZIP de Reportes Listo — Revisión de Medidores');
});

it('la notificación guarda el tipo y url correctos en la base de datos', function () {
    $url = 'https://example.com/zip-signed';
    $notification = new ZipReportesListoNotification($url, 2);
    $data = $notification->toArray($this->user);

    expect($data['type'])->toBe('zip_reportes_listo');
    expect($data['url'])->toBe($url);
    expect($data['cantidad'])->toBe(2);
});
