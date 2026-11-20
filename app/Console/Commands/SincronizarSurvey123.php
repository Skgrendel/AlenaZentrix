<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\survey123\Survey123Client;
use App\Services\survey123\Survey123Sincronizador;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Throwable;

class SincronizarSurvey123 extends Command
{
    protected $signature = 'survey123:sincronizar
        {--company= : ID de la empresa dueña de los registros (si hay una sola, se usa esa)}
        {--desde= : Solo registros editados desde esta fecha (Y-m-d o Y-m-d H:i)}
        {--sin-fotos : No copiar las fotos}
        {--json= : Ruta de un archivo JSON donde volcar los datos de ArcGIS, sin escribir en la base de datos}';

    protected $description = 'Trae las suspensiones de Survey123 a survey_registros (y sus fotos a S3).';

    public function handle(): int
    {
        try {
            $cliente = Survey123Client::desdeConfig();

            if ($this->option('json')) {
                return $this->exportarJson($cliente, (string) $this->option('json'));
            }

            $companyId = $this->resolverEmpresa();

            if ($companyId === null) {
                return self::FAILURE;
            }

            $desde = $this->option('desde') ? Carbon::parse($this->option('desde')) : null;

            $resumen = (new Survey123Sincronizador($cliente))
                ->sincronizar($companyId, $desde, ! $this->option('sin-fotos'));
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Nuevos: {$resumen['nuevos']} | Actualizados: {$resumen['actualizados']} | Sin cambios: {$resumen['sin_cambios']} | Fotos: {$resumen['fotos']} | Errores: {$resumen['errores']}");

        return $resumen['errores'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function exportarJson(Survey123Client $cliente, string $ruta): int
    {
        $metadatos = $cliente->metadatos();
        $campoId = $metadatos['objectIdField'] ?? 'OBJECTID';

        $features = iterator_to_array($cliente->registros('1=1', $campoId), false);
        $adjuntos = $cliente->adjuntos(array_map(fn (array $f): mixed => $f['attributes'][$campoId] ?? null, $features));

        File::ensureDirectoryExists(dirname($ruta));
        File::put($ruta, json_encode([
            'campos' => array_map(fn (array $c): array => [
                'name' => $c['name'],
                'alias' => $c['alias'] ?? null,
                'type' => $c['type'] ?? null,
                'dominio' => array_map(fn (array $v): array => [$v['code'], $v['name']], $c['domain']['codedValues'] ?? []),
            ], $metadatos['fields'] ?? []),
            'registros' => $features,
            'adjuntos' => $adjuntos,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $this->info(count($features).' registros exportados a '.$ruta);

        return self::SUCCESS;
    }

    private function resolverEmpresa(): ?string
    {
        if ($this->option('company')) {
            return (string) $this->option('company');
        }

        $empresas = Company::query()->limit(2)->pluck('id');

        if ($empresas->count() !== 1) {
            $this->error('Hay varias empresas: indica cuál con --company=ID.');

            return null;
        }

        return $empresas->first();
    }
}
