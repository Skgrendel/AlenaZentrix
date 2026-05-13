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
            $table->foreignUuid('creado_por_id')->nullable()->constrained('users')->nullOnDelete();

            // Datos del punto de servicio
            $table->string('tipo_servicio')->default('fraudes');
            $table->string('numero_orden');
            $table->string('contrato')->nullable();
            $table->string('cliente')->nullable();
            $table->string('direccion')->nullable();
            $table->string('barrio')->nullable();
            $table->string('medidor')->nullable();
            $table->text('antecedente')->nullable();
            $table->text('objetivo')->nullable();
            $table->json('actividades')->nullable();
            $table->string('tipo_medidor')->nullable();
            $table->string('ciclo')->nullable();
            $table->string('numero_noticia_criminal')->nullable();
            $table->string('tipo_investigacion')->nullable(); // 'interna' | 'contratista' (solo para Investigación)
            $table->string('tipo_gestion_legal')->nullable(); // sub-tipo para GestionLegal
            $table->json('documentos')->nullable();

            // Gestión de la orden
            $table->enum('prioridad', ['baja', 'media', 'alta', 'urgente'])->default('media'); // baja | media | alta | urgente
            $table->enum('estado', ['pendiente', 'asignada', 'en_progreso', 'en_revision', 'completada', 'rechazada', 'cancelada'])->default('pendiente');
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
