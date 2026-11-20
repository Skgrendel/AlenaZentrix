<?php

namespace App\Console\Commands;

use App\Jobs\RevisarFotosIAJob;
use App\Models\RevisionFotosIA;
use App\Models\SurveyRegistro;
use Illuminate\Console\Command;

class RevisarFotosIAPendientes extends Command
{
    protected $signature = 'survey123:revisar-ia
        {--limite=100 : Máximo de registros a encolar en esta corrida}
        {--company= : Solo los registros de esta empresa}
        {--simular : Solo cuenta y estima el costo, sin encolar nada}';

    protected $description = 'Encola la revisión con IA de los registros con fotos que no tienen revisión (o cuyas fotos son más nuevas que su última revisión).';

    public function handle(): int
    {
        if (blank(config('services.openai.api_key'))) {
            $this->error('Falta OPENAI_API_KEY en la configuración.');

            return self::FAILURE;
        }

        $query = SurveyRegistro::query()
            ->withoutGlobalScopes()
            ->whereHas('adjuntos')
            ->where(function ($q): void {
                $q->whereDoesntHave('revisionesIA')
                    ->orWhereRaw('(select max(created_at) from revisiones_ia_survey where survey_registro_id = survey_registros.id) < (select max(created_at) from survey_adjuntos where survey_registro_id = survey_registros.id)');
            })
            ->when($this->option('company'), fn ($q, $empresa) => $q->where('company_id', $empresa))
            ->orderBy('fecha_registro');

        $pendientes = (clone $query)->count();
        $registros = $query->limit(max(1, (int) $this->option('limite')))->pluck('id');

        $promedio = RevisionFotosIA::query()->whereNotNull('costo_usd')->avg('costo_usd');
        $estimado = $promedio === null ? 'sin datos para estimar' : sprintf('~US$ %.2f', $registros->count() * $promedio);

        $this->line("Pendientes de revisión con IA: {$pendientes}. En esta corrida: {$registros->count()} ({$estimado}).");

        if ($this->option('simular') || $registros->isEmpty()) {
            return self::SUCCESS;
        }

        $registros->each(fn (string $id) => RevisarFotosIAJob::dispatch($id, automatica: false));

        $this->info("{$registros->count()} revisiones encoladas. Las procesa la cola.");

        return self::SUCCESS;
    }
}
