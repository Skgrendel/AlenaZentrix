<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reportes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ubicacions_id')->constrained('ubicacions')->cascadeOnDelete();
            $table->foreignUuid('comercios_id')->constrained('comercios')->cascadeOnDelete();
            $table->foreignUuid('orden_job_id')->constrained('orden_jobs')->cascadeOnDelete();
            $table->string('numero_orden');
            $table->string('numero_medidor')->nullable();
            $table->string('lectura');
            $table->json('anomalia')->nullable();
            $table->uuid('tipo_imposibilidad_id')->nullable();
            $table->uuid('tipo_regulador_id')->nullable();
            $table->uuid('marca_medidor_id')->nullable();
            $table->uuid('marca_regulador_id')->nullable();
            $table->json('imagenes')->nullable();
            $table->string('video')->nullable();
            $table->text('observaciones')->nullable();
            $table->text('comentarios')->nullable();
            $table->string('cau')->nullable();
            $table->boolean('revisado')->default(false);
            $table->boolean('confirmado')->default(false);
            $table->string('estado')->default('pendiente');
            $table->timestamps();
        });

        // Añadir FK a `auditorias.reportes_id` ahora que `reportes` existe
        Schema::table('auditorias', function (Blueprint $table) {
            if (Schema::hasColumn('auditorias', 'reportes_id')) {
                $table->foreign('reportes_id')
                    ->references('id')
                    ->on('reportes')
                    ->cascadeOnDelete();
            }
        });
    }

    public function down(): void
    {
        // Eliminar la FK en auditorias antes de soltar la tabla reportes
        if (Schema::hasTable('auditorias')) {
            Schema::table('auditorias', function (Blueprint $table) {
                if (Schema::hasColumn('auditorias', 'reportes_id')) {
                    $table->dropForeign(['reportes_id']);
                }
            });
        }

        Schema::dropIfExists('reportes');
    }
};
