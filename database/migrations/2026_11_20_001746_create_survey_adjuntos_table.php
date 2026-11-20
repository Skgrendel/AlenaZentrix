<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fotos de cada registro de Survey123, copiadas a nuestro almacenamiento.
     */
    public function up(): void
    {
        Schema::create('survey_adjuntos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('survey_registro_id')->constrained('survey_registros')->cascadeOnDelete();
            $table->unsignedBigInteger('attachment_id');
            $table->string('global_id', 64)->nullable();
            $table->string('campo', 100)->nullable();
            $table->string('nombre');
            $table->string('content_type', 100)->nullable();
            $table->unsignedBigInteger('tamano')->nullable();
            $table->string('ruta')->nullable();
            $table->string('hash', 40)->nullable();
            $table->timestamps();

            $table->unique(['survey_registro_id', 'attachment_id']);
            $table->index('hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_adjuntos');
    }
};
