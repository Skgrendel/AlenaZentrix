<?php

namespace App\Jobs;

use App\Models\AjusteRevisionIA;
use App\Models\SurveyRegistro;
use App\Services\survey123\RevisorFotosIA;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Revisa con IA las fotos de un registro y guarda el veredicto como sugerencia (no cambia la auditoría).
 * Un job por registro: si el proveedor tarda o falla, no afecta a la sincronización ni a los demás registros.
 */
class RevisarFotosIAJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300];
    }

    /**
     * @param  bool  $automatica  true cuando la lanza la sincronización: respeta el interruptor de Configuración.
     */
    public function __construct(public readonly string $registroId, public readonly bool $automatica = true) {}

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->registroId))->releaseAfter(60)->expireAfter(600)];
    }

    public function handle(RevisorFotosIA $revisor): void
    {
        $registro = SurveyRegistro::query()->withoutGlobalScopes()->find($this->registroId);

        if ($registro === null || blank(config('services.openai.api_key'))) {
            return;
        }

        if ($this->automatica && ! AjusteRevisionIA::de($registro->company_id)->revision_automatica) {
            return;
        }

        $ultimaFoto = $registro->adjuntos()->max('created_at');

        if ($ultimaFoto === null || $this->yaRevisadoConEstasFotos($registro, (string) $ultimaFoto)) {
            return;
        }

        $revisor->revisarYGuardar($registro);
    }

    public function failed(Throwable $e): void
    {
        Log::warning('No se pudo revisar con IA las fotos de un registro.', ['registro' => $this->registroId, 'error' => $e->getMessage()]);
    }

    /** Evita pagar dos veces por lo mismo si el job se encola de nuevo sin que hayan llegado fotos. */
    private function yaRevisadoConEstasFotos(SurveyRegistro $registro, string $ultimaFoto): bool
    {
        $ultimaRevision = $registro->revisionesIA()->max('created_at');

        return $ultimaRevision !== null && $ultimaRevision >= $ultimaFoto;
    }
}
