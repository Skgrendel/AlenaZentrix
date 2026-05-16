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
            $table->foreignUuid('ubicacions_id')->nullable()->constrained('ubicacions')->nullOnDelete();
            $table->foreignUuid('comercios_id')->nullable()->constrained('comercios')->nullOnDelete();
            $table->foreignUuid('orden_job_id')->constrained('orden_jobs')->cascadeOnDelete();
            $table->foreignUuid('personals_id')->nullable()->constrained('personals')->nullOnDelete();
            $table->string('numero_orden');
            $table->string('numero_medidor')->nullable();
            $table->string('lectura')->nullable();
            $table->json('datos_servicio')->nullable();
            $table->json('anomalia')->nullable();
            $table->json('actividades')->nullable();
            $table->uuid('tipo_imposibilidad_id')->nullable();
            $table->uuid('tipo_regulador_id')->nullable();
            $table->uuid('marca_medidor_id')->nullable();
            $table->uuid('marca_regulador_id')->nullable();
            $table->json('imagenes')->nullable();
            $table->text('video')->nullable();
            $table->uuid('tipo_notificacion_alerta_id')->nullable();
            $table->boolean('coincide_medidor')->nullable();
            $table->boolean('hay_fuga_gas')->default(false);
            $table->boolean('excede_capacidad')->default(false);
            $table->text('observaciones')->nullable();
            $table->text('comentarios')->nullable();
            $table->text('conclusion')->nullable();
            $table->boolean('revisado')->default(false);
            $table->boolean('confirmado')->default(false);
            $table->string('estado')->default('pendiente');
            $table->foreignUuid('company_id')->nullable()->constrained('companies')->nullOnDelete();
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
