<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Veredictos de la IA sobre las fotos de cada registro. Son solo una sugerencia para la persona que revisa.
     */
    public function up(): void
    {
        Schema::create('revisiones_ia_survey', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('survey_registro_id')->constrained('survey_registros')->cascadeOnDelete();
            $table->string('modelo', 100);
            $table->string('detalle_imagen', 10);
            $table->string('instrucciones_hash', 40);
            $table->json('veredicto');
            $table->unsignedInteger('fotos');
            $table->unsignedInteger('tokens_entrada');
            $table->unsignedInteger('tokens_salida');
            $table->decimal('costo_usd', 10, 5)->nullable();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['survey_registro_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revisiones_ia_survey');
    }
};
