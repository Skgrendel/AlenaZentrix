<?php

namespace App\Jobs;

use App\Models\SurveyRegistro;
use App\Services\survey123\AuditoriaSurveyRegistro;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Vuelve a aplicar las reglas de auditoría a todos los registros de una empresa, por ejemplo después de cambiar
 * qué tan grave es cada regla. No toca el estado ni las fotos: solo recalcula los hallazgos y sus totales.
 */
class ReauditarRegistrosJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(public readonly string $companyId) {}

    public function handle(): void
    {
        $auditor = new AuditoriaSurveyRegistro;

        SurveyRegistro::query()
            ->withoutGlobalScopes()
            ->where('company_id', $this->companyId)
            ->chunkById(200, function ($registros) use ($auditor): void {
                foreach ($registros as $registro) {
                    $auditor->auditarYGuardar($registro);
                }
            });
    }
}
