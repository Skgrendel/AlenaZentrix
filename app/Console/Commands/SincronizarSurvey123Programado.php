<?php

namespace App\Console\Commands;

use App\Jobs\SincronizarSurvey123Job;
use App\Models\Company;
use App\Services\survey123\EstadoSincronizacion;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Lo ejecuta el programador cada pocos minutos. Solo lanza la sincronización si ya pasó el intervalo
 * configurado desde la última (la manual cuenta igual), si no hay otra en curso y si hay credenciales.
 */
class SincronizarSurvey123Programado extends Command
{
    protected $signature = 'survey123:sincronizar-programado';

    protected $description = 'Lanza la sincronización automática de registros cuando ya toca.';

    private const INTERVALO_MINIMO_MINUTOS = 5;

    public function handle(EstadoSincronizacion $estado): int
    {
        if (! config('services.survey123.sincronizacion_automatica')) {
            return self::SUCCESS;
        }

        if (blank(config('services.survey123.usuario')) || blank(config('services.survey123.password'))) {
            $this->warn('Sincronización automática omitida: faltan las credenciales.');

            return self::SUCCESS;
        }

        $empresa = $this->empresa();

        if ($empresa === null) {
            $this->warn('Sincronización automática omitida: hay varias empresas y no se indicó cuál (SURVEY123_EMPRESA_ID).');

            return self::SUCCESS;
        }

        $intervalo = max(self::INTERVALO_MINIMO_MINUTOS, (int) config('services.survey123.sincronizacion_cada_minutos'));
        $fin = $estado->estado($empresa)['fin'] ?? null;

        if ($fin !== null && Carbon::parse($fin)->addMinutes($intervalo)->isFuture()) {
            return self::SUCCESS;
        }

        if ($estado->iniciar($empresa, 'Automática') !== null) {
            return self::SUCCESS;
        }

        SincronizarSurvey123Job::dispatch($empresa);
        $this->info('Sincronización automática puesta en cola.');

        return self::SUCCESS;
    }

    private function empresa(): ?string
    {
        if (filled(config('services.survey123.empresa_id'))) {
            return (string) config('services.survey123.empresa_id');
        }

        $empresas = Company::query()->limit(2)->pluck('id');

        return $empresas->count() === 1 ? (string) $empresas->first() : null;
    }
}
