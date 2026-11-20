<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Registros de suspensiones reportados por los técnicos en Survey123, pendientes de auditar.
     */
    public function up(): void
    {
        Schema::create('survey_registros', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->unsignedBigInteger('object_id')->nullable();
            $table->string('global_id', 64)->nullable();
            $table->dateTime('fecha_registro')->nullable();
            $table->string('tecnico')->nullable();
            $table->string('contrato', 50)->nullable();
            $table->string('orden_trabajo', 50)->nullable();
            $table->string('categoria', 20)->nullable();
            $table->string('serie_medidor', 50)->nullable();
            $table->string('lectura_medidor', 50)->nullable();
            $table->string('actividad', 20)->nullable();
            $table->boolean('suspension_ejecutada')->nullable();
            $table->string('metodo_suspension', 50)->nullable();
            $table->string('serie_sonda', 50)->nullable();
            $table->string('serie_caps', 50)->nullable();
            $table->string('motivo_no_ejecucion', 50)->nullable();
            $table->text('justificacion')->nullable();
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();
            $table->dateTime('creado_en_origen')->nullable();
            $table->dateTime('editado_en_origen')->nullable();
            $table->json('payload')->nullable();
            $table->string('estado', 30)->default('pendiente');
            $table->timestamps();

            $table->unique(['company_id', 'global_id']);
            $table->index('orden_trabajo');
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_registros');
    }
};
