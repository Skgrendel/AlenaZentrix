<?php

namespace App\Services\survey123;

use App\Models\SurveyAdjunto;
use App\Models\SurveyRegistro;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Copia los registros de Survey123 (y sus fotos) a survey_registros y survey_adjuntos.
 * Es idempotente: cada registro se identifica por su GlobalID y solo se actualiza si cambió en el origen.
 */
class Survey123Sincronizador
{
    public function __construct(private readonly Survey123Client $cliente) {}

    /**
     * @return array{nuevos: int, actualizados: int, sin_cambios: int, fotos: int, errores: int}
     */
    public function sincronizar(string $companyId, ?Carbon $desde = null, bool $conFotos = true): array
    {
        $mapeador = new Survey123Mapeador($this->cliente->metadatos());

        $where = $desde
            ? "{$mapeador->campoEdicion()} >= timestamp '{$desde->copy()->utc()->format('Y-m-d H:i:s')}'"
            : '1=1';

        $resumen = ['nuevos' => 0, 'actualizados' => 0, 'sin_cambios' => 0, 'fotos' => 0, 'errores' => 0];
        $lote = [];

        foreach ($this->cliente->registros($where, $mapeador->campoId()) as $feature) {
            $lote[] = $mapeador->mapear($feature);

            if (count($lote) === 50) {
                $this->procesarLote($lote, $companyId, $conFotos, $resumen);
                $lote = [];
            }
        }

        if ($lote !== []) {
            $this->procesarLote($lote, $companyId, $conFotos, $resumen);
        }

        return $resumen;
    }

    /**
     * @param  array<int, array<string, mixed>>  $lote
     * @param  array<string, int>  $resumen
     */
    private function procesarLote(array $lote, string $companyId, bool $conFotos, array &$resumen): void
    {
        $adjuntosPorObjeto = $conFotos
            ? $this->cliente->adjuntos(array_column($lote, 'object_id'))
            : [];

        foreach ($lote as $datos) {
            if (blank($datos['global_id'])) {
                $resumen['errores']++;

                continue;
            }

            $registro = SurveyRegistro::query()
                ->withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('global_id', $datos['global_id'])
                ->first();

            $cambio = $registro === null
                || $registro->editado_en_origen === null
                || ($datos['editado_en_origen'] !== null && $datos['editado_en_origen']->getTimestamp() > $registro->editado_en_origen->getTimestamp());

            if ($registro === null) {
                $registro = SurveyRegistro::query()->create($datos + ['company_id' => $companyId]);
                $resumen['nuevos']++;
            } elseif ($cambio) {
                $registro->update($datos);
                $resumen['actualizados']++;
            } else {
                $resumen['sin_cambios']++;
            }

            $fotosNuevas = 0;

            if ($conFotos) {
                $fotosNuevas = $this->guardarAdjuntos($registro, $adjuntosPorObjeto[$datos['object_id']] ?? [], $resumen);
                $resumen['fotos'] += $fotosNuevas;
            }

            if ($cambio || $fotosNuevas > 0 || $registro->auditado_en === null) {
                (new AuditoriaSurveyRegistro)->auditarYGuardar($registro->unsetRelation('adjuntos'));
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $infos
     * @param  array<string, int>  $resumen
     */
    private function guardarAdjuntos(SurveyRegistro $registro, array $infos, array &$resumen): int
    {
        $guardados = 0;

        foreach ($infos as $info) {
            $existe = SurveyAdjunto::query()
                ->where('survey_registro_id', $registro->id)
                ->where('attachment_id', $info['id'])
                ->exists();

            if ($existe) {
                continue;
            }

            try {
                $contenido = $this->cliente->descargar($registro->object_id, $info['id']);
                $ruta = "survey123/{$registro->company_id}/{$registro->id}/{$info['id']}-{$info['name']}";

                $disco = Storage::disk(config('services.survey123.disco_fotos'));

                // Los discos del proyecto no lanzan excepción al fallar: hay que revisar el resultado.
                if ($disco->put($ruta, $contenido) === false) {
                    throw new RuntimeException("No se pudo guardar la foto {$info['name']} en el almacenamiento.");
                }

                SurveyAdjunto::query()->create([
                    'survey_registro_id' => $registro->id,
                    'attachment_id' => $info['id'],
                    'global_id' => $info['globalId'] ?? null,
                    'campo' => $info['keywords'] ?? null,
                    'nombre' => $info['name'],
                    'content_type' => $info['contentType'] ?? null,
                    'tamano' => $info['size'] ?? strlen($contenido),
                    'ruta' => $ruta,
                    'hash' => sha1($contenido),
                ]);

                $guardados++;
            } catch (Throwable $e) {
                $resumen['errores']++;
                Log::warning('Survey123: no se pudo copiar un adjunto.', [
                    'registro' => $registro->id,
                    'adjunto' => $info['id'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $guardados;
    }
}
