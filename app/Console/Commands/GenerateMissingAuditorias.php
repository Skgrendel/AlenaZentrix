<?php

namespace App\Console\Commands;

use App\Enums\EstadoAuditoria;
use App\Enums\EstadoOrden;
use App\Models\auditoria;
use App\Models\OrdenJob;
use Illuminate\Console\Command;

class GenerateMissingAuditorias extends Command
{
    protected $signature = 'app:generate-missing-auditorias';

    protected $description = 'Genera auditorias para ordenes completadas sin auditoria asociada';

    public function handle()
    {
        // Obtener todas las órdenes completadas
        $ordenes = OrdenJob::where('estado', EstadoOrden::Completada)
            ->get();

        if ($ordenes->isEmpty()) {
            $this->info('✓ No se encontraron órdenes completadas.');

            return;
        }

        $creadas = 0;

        foreach ($ordenes as $orden) {
            $reporte = $orden->reportes()->latest()->first();

            if ($reporte) {
                // Verificar si el reporte ya tiene auditoría
                if (! auditoria::where('reportes_id', $reporte->id)->exists()) {
                    auditoria::create([
                        'reportes_id' => $reporte->id,
                        'estado' => EstadoAuditoria::PendienteAuditoria,
                    ]);
                    $creadas++;
                }
            }
        }

        $this->info("✓ Se crearon {$creadas} registros de auditoría.");
    }
}
