<?php

namespace App\Console\Commands;

use App\Enums\EstadoAuditoria;
use App\Models\auditoria;
use Illuminate\Console\Command;

/**
 * Corrige auditorías que quedaron en 'pendiente_auditoria' para órdenes cuyo resultado
 * es 'negativo'. Estas se generaron por un bug anterior a la corrección en
 * OrdenJob::booted() (hook 'updated'), que ahora solo crea auditoría cuando el
 * resultado de la orden es 'positivo'. Nunca debieron quedar pendientes por auditar.
 */
class FixOrphanedNegativeAuditorias extends Command
{
    protected $signature = 'app:fix-orphaned-negative-auditorias {--force : Aplica los cambios; sin este flag solo se muestra un listado (dry-run)}';

    protected $description = 'Marca como Negativo las auditorías pendientes cuya orden tiene resultado negativo';

    public function handle(): int
    {
        $auditorias = auditoria::query()
            ->where('estado', EstadoAuditoria::PendienteAuditoria)
            ->whereHas('reporte.ordenJob', fn ($q) => $q->where('resultado', 'negativo'))
            ->with('reporte.ordenJob')
            ->get();

        if ($auditorias->isEmpty()) {
            $this->info('✓ No se encontraron auditorías huérfanas.');

            return self::SUCCESS;
        }

        $this->table(
            ['Auditoria ID', 'Orden', 'Tiene evidencias/observaciones'],
            $auditorias->map(fn (auditoria $a) => [
                $a->id,
                $a->reporte?->ordenJob?->numero_orden,
                (! empty($a->evidencias) || ! empty($a->observaciones)) ? 'SÍ' : 'no',
            ])
        );

        if (! $this->option('force')) {
            $this->warn("Dry-run: {$auditorias->count()} auditorías serían marcadas como Negativo. Ejecuta con --force para aplicar.");

            return self::SUCCESS;
        }

        foreach ($auditorias as $a) {
            $a->update(['estado' => EstadoAuditoria::Negativo]);
        }

        $this->info("✓ Se marcaron {$auditorias->count()} auditorías como Negativo.");

        return self::SUCCESS;
    }
}
