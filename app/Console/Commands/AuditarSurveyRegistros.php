<?php

namespace App\Console\Commands;

use App\Models\SurveyRegistro;
use App\Services\survey123\AuditoriaSurveyRegistro;
use Illuminate\Console\Command;

class AuditarSurveyRegistros extends Command
{
    protected $signature = 'survey123:auditar {--company= : Solo los registros de esta empresa}';

    protected $description = 'Vuelve a aplicar las reglas de auditoría a todos los registros de Survey123.';

    public function handle(AuditoriaSurveyRegistro $auditor): int
    {
        $query = SurveyRegistro::query()->withoutGlobalScopes();

        if ($this->option('company')) {
            $query->where('company_id', $this->option('company'));
        }

        $total = 0;

        $query->chunkById(100, function ($registros) use ($auditor, &$total): void {
            foreach ($registros as $registro) {
                $auditor->auditarYGuardar($registro);
                $total++;
            }
        });

        $this->info("{$total} registros auditados.");

        return self::SUCCESS;
    }
}
