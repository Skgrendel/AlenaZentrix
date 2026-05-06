<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditorias', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('reportes_id');
            $table->foreignUuid('auditado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('estado')->default('pendiente_auditoria');
            $table->json('evidencias')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamp('fecha_auditoria')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditorias');
    }
};
