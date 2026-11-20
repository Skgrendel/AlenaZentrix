<?php

namespace App\Jobs;

use App\Services\survey123\EstadoSincronizacion;
use App\Services\survey123\Survey123Client;
use App\Services\survey123\Survey123Sincronizador;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sincroniza en segundo plano los registros de Survey123 de una empresa, informando su avance.
 * Las corridas posteriores a la primera solo piden lo editado desde un día antes de la última exitosa.
 */
class SincronizarSurvey123Job implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(public readonly string $companyId) {}

    public function handle(EstadoSincronizacion $estado): void
    {
        $estado->enProceso($this->companyId, 'Conectando');

        try {
            $ultimaExitosa = $estado->estado($this->companyId)['ultima_exitosa'] ?? null;
            $desde = $ultimaExitosa ? Carbon::parse($ultimaExitosa)->subDay() : null;

            $resumen = (new Survey123Sincronizador(Survey123Client::desdeConfig()))->sincronizar(
                $this->companyId,
                $desde,
                true,
                fn (array $parcial, string $etapa) => $estado->avance($this->companyId, $parcial, $etapa),
            );

            $estado->terminar($this->companyId, $resumen);
        } catch (Throwable $e) {
            $this->registrarFallo($estado, $e);

            throw $e;
        }
    }

    /** Se llama también cuando el proceso de colas aborta el job (por ejemplo, por tiempo). */
    public function failed(Throwable $e): void
    {
        $this->registrarFallo(app(EstadoSincronizacion::class), $e);
    }

    private function registrarFallo(EstadoSincronizacion $estado, Throwable $e): void
    {
        Log::error('Falló la sincronización con Survey123.', ['empresa' => $this->companyId, 'error' => $e->getMessage()]);

        $estado->fallar($this->companyId, 'No se pudo completar la sincronización. Revisa la conexión e inténtalo más tarde.');
    }
}
