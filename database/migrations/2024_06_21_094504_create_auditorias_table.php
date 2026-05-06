<?php

use App\Enums\EstadoAuditoria;
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
            $table->enum('estado', array_column(EstadoAuditoria::cases(), 'value'))
                ->default(EstadoAuditoria::PendienteAuditoria->value);
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
