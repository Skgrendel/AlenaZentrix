<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Qué tan graves son los hallazgos de cada regla de la auditoría de suspensiones, por empresa:
     * bloqueante (propone «No se aprueba»), corregible (propone «Se aprueba solicitando correcciones») o no aplicar.
     */
    public function up(): void
    {
        Schema::create('ajustes_auditoria', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->unique()->constrained('companies')->cascadeOnDelete();
            $table->json('pesos');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ajustes_auditoria');
    }
};
