<?php

namespace App\Console\Commands;

use App\Models\SurveyRegistro;
use App\Services\survey123\RevisorFotosIA;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProbarRevisionIASurvey extends Command
{
    protected $signature = 'survey123:probar-ia
        {--registro=* : OT de los registros a revisar (por defecto, todos los que tienen fotos)}
        {--limite=3 : Máximo de registros a revisar}';

    protected $description = 'Prueba la revisión de fotos con IA en modo sombra: muestra el veredicto y el costo, sin modificar la auditoría.';

    public function handle(RevisorFotosIA $revisor): int
    {
        $query = SurveyRegistro::query()->withoutGlobalScopes()->whereHas('adjuntos')->orderBy('fecha_registro');

        if ($this->option('registro')) {
            $query->whereIn('orden_trabajo', $this->option('registro'));
        }

        $registros = $query->limit((int) $this->option('limite'))->get();
        $resultados = [];
        $costo = 0.0;

        foreach ($registros as $registro) {
            $this->line("OT {$registro->orden_trabajo}…");

            try {
                $r = $revisor->revisar($registro);
            } catch (Throwable $e) {
                $this->error("  No se pudo revisar: {$e->getMessage()}");

                continue;
            }

            $costo += (float) $r['costo_usd'];
            $resultados[] = ['orden_trabajo' => $registro->orden_trabajo] + $r;

            $this->table(
                ['#', 'Campo declarado', 'Muestra', 'Coincide', 'Calidad', 'Observación'],
                collect($r['veredicto']['fotos'])->map(fn ($f) => [
                    $f['numero'], $f['campo_declarado'], $f['muestra'], $f['coincide_con_campo'] ? 'sí' : 'NO', $f['calidad'], $f['observacion'],
                ])->all()
            );
            $this->line('  Repetidas: '.($r['veredicto']['fotos_repetidas'] ? 'sí' : 'no').' | Suspensión visible: '.json_encode($r['veredicto']['suspension_visible']));
            $this->line('  Datos leídos: '.json_encode($r['veredicto']['datos_leidos'], JSON_UNESCAPED_UNICODE));

            foreach ($r['veredicto']['hallazgos'] as $h) {
                $this->line("  [{$h['severidad']}] {$h['detalle']}");
            }

            $this->line("  Resumen: {$r['veredicto']['resumen']}");
            $this->line(sprintf('  %s: %d fotos, %d tokens de entrada, %d de salida, US$ %.4f', $r['modelo'], $r['fotos'], $r['tokens_entrada'], $r['tokens_salida'], $r['costo_usd'] ?? 0));
        }

        if ($resultados === []) {
            return self::FAILURE;
        }

        $archivo = 'pruebas-ia/revision-'.now()->format('Ymd-His').'.json';
        Storage::disk('local')->put($archivo, json_encode($resultados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $fotos = array_sum(array_column($resultados, 'fotos'));
        $this->newLine();
        $this->info(sprintf(
            '%d registros, %d fotos, US$ %.4f en total (US$ %.4f por registro, US$ %.5f por foto). Resultado guardado en storage/app/private/%s',
            count($resultados), $fotos, $costo, $costo / count($resultados), $costo / max(1, $fotos), $archivo
        ));

        return self::SUCCESS;
    }
}
