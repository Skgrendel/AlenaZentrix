<?php

use App\Enums\EstadoAuditoria;
use App\Enums\EstadoOrden;
use App\Enums\TipoServicio;
use App\Livewire\Coordinador\ReporteRevisar;
use App\Models\auditoria;
use App\Models\Company;
use App\Models\OrdenJob;
use App\Models\personals;
use App\Models\reportes;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function crearOrdenCompletadaConReporte(Company $company): array
{
    $personal = personals::factory()->create(['company_id' => $company->id]);
    $orden = OrdenJob::factory()->create([
        'tipo_servicio' => TipoServicio::Fraudes->value,
        'company_id' => $company->id,
        'personals_id' => $personal->id,
        'estado' => EstadoOrden::Completada->value,
        'resultado' => 'positivo',
    ]);
    $reporte = reportes::factory()->create([
        'orden_job_id' => $orden->id,
        'personals_id' => $personal->id,
        'numero_orden' => $orden->numero_orden,
    ]);

    return [$orden, $reporte];
}

it('muestra el resultado de la auditoria en Anexos de la Orden cuando existe', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $this->actingAs($user);

    [$orden, $reporte] = crearOrdenCompletadaConReporte($company);

    auditoria::create([
        'reportes_id' => $reporte->id,
        'estado' => EstadoAuditoria::Confirmado,
        'observaciones' => 'Se confirmó la anomalía.',
    ]);

    Livewire::test(ReporteRevisar::class, ['orden' => $orden])
        ->assertSee('Resultado de la Auditoría')
        ->assertSee('Confirmado')
        ->assertSee('Se confirmó la anomalía.');
});

it('muestra pendiente por auditar cuando la auditoria aun no se registro', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $this->actingAs($user);

    [$orden, $reporte] = crearOrdenCompletadaConReporte($company);

    auditoria::create([
        'reportes_id' => $reporte->id,
        'estado' => EstadoAuditoria::PendienteAuditoria,
    ]);

    Livewire::test(ReporteRevisar::class, ['orden' => $orden])
        ->assertSee('Resultado de la Auditoría')
        ->assertSee('Pendiente por auditar');
});

it('muestra las evidencias de la auditoria como galeria de imagenes y lista de documentos', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $this->actingAs($user);

    [$orden, $reporte] = crearOrdenCompletadaConReporte($company);

    auditoria::create([
        'reportes_id' => $reporte->id,
        'estado' => EstadoAuditoria::Confirmado,
        'evidencias' => ['auditorias/evidencia-1.jpg', 'auditorias/soporte.pdf'],
    ]);

    Livewire::test(ReporteRevisar::class, ['orden' => $orden])
        ->assertSee('Evidencias de la Auditoría')
        ->assertSee('soporte.pdf');
});

it('no muestra la seccion de auditoria cuando la orden no tiene auditoria asociada', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $this->actingAs($user);

    [$orden] = crearOrdenCompletadaConReporte($company);

    Livewire::test(ReporteRevisar::class, ['orden' => $orden])
        ->assertDontSee('Resultado de la Auditoría');
});
