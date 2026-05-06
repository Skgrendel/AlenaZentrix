<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orden_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('personals_id')->nullable();

            // Datos del punto de servicio (gas)
            $table->string('numero_orden');
            $table->string('contrato');
            $table->string('cliente');
            $table->string('direccion');
            $table->string('barrio');
            $table->string('medidor');
            $table->string('tipo_medidor')->nullable();
            $table->string('ciclo');
            $table->json('fotos')->nullable();

            // Gestión de la orden
            $table->string('titulo')->nullable();
            $table->text('descripcion')->nullable();
            $table->enum('prioridad', ['baja', 'media', 'alta', 'urgente'])->default('media'); // baja | media | alta | urgente
            $table->enum('estado', ['pendiente', 'asignada', 'en_progreso', 'completada', 'cancelada'])->default('pendiente'); // pendiente | asignada | en_progreso | completada | cancelada
            $table->boolean('estado_servicio')->default(true);

            // Fechas de seguimiento
            $table->timestamp('fecha_asignacion')->nullable();
            $table->timestamp('fecha_limite')->nullable();
            $table->timestamp('fecha_completado')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orden_jobs');
    }
};
