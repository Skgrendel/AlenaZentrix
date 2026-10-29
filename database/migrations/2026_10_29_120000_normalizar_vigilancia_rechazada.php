<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Las órdenes de Vigilancia ya no pasan a "rechazada": el rechazo marca cada turno con
     * requiere_correccion y la orden sigue en progreso. Las que quedaron rechazadas con el flujo
     * anterior se reabren, con sus turnos marcados para corrección, para que el técnico pueda
     * corregirlos o agregar turnos nuevos.
     */
    public function up(): void
    {
        $ordenes = DB::table('orden_jobs')
            ->where('tipo_servicio', 'vigilancia')
            ->where('estado', 'rechazada')
            ->pluck('id');

        if ($ordenes->isEmpty()) {
            return;
        }

        DB::table('reportes')
            ->whereIn('orden_job_id', $ordenes)
            ->update(['requiere_correccion' => true]);

        DB::table('orden_jobs')
            ->whereIn('id', $ordenes)
            ->update(['estado' => 'en_progreso']);
    }

    public function down(): void
    {
        // No se revierte: no hay forma de saber qué órdenes estaban rechazadas antes.
    }
};
