<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajustes de la revisión de fotos con IA: modelo, detalle de imagen e instrucciones, por empresa.
     */
    public function up(): void
    {
        Schema::create('ajustes_revision_ia', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->unique()->constrained('companies')->cascadeOnDelete();
            $table->string('modelo', 100);
            $table->string('detalle_imagen', 10)->default('low');
            $table->text('instrucciones');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ajustes_revision_ia');
    }
};
