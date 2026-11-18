<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Las órdenes rechazadas anteriores a que el rechazo reiniciara el flag quedaron con
     * sincronizado=true; la app móvil las tomaba como ya sincronizadas y no permitía corregirlas.
     */
    public function up(): void
    {
        DB::table('orden_jobs')
            ->where('estado', 'rechazada')
            ->where('sincronizado', true)
            ->update(['sincronizado' => false]);
    }

    public function down(): void
    {
        // Irreversible de forma segura: no se puede saber cuáles estaban sincronizadas.
    }
};
