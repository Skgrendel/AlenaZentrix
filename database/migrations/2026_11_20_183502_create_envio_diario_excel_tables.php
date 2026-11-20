<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Envío diario del Excel de la auditoría de suspensiones por correo: los ajustes de cada empresa
     * (hora, días, destinatarios, asunto y mensaje) y el registro de lo que se envió.
     */
    public function up(): void
    {
        Schema::create('ajustes_envio_excel', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->unique()->constrained('companies')->cascadeOnDelete();
            $table->boolean('activo')->default(false);
            // Desde cuándo está encendido: el primer correo trae lo que llegue a partir de ese momento.
            $table->timestamp('activo_desde')->nullable();
            $table->string('hora', 5)->default('18:00');
            $table->json('dias');
            $table->text('destinatarios');
            $table->string('asunto', 150);
            $table->text('mensaje');
            $table->timestamps();
        });

        Schema::create('envios_excel', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('origen', 20);
            $table->string('estado', 20);
            $table->json('destinatarios');
            // Ventana de registros que cubrió el envío (por el momento en que llegaron al sistema): el siguiente empieza en $hasta.
            $table->timestamp('desde')->nullable();
            $table->timestamp('hasta')->nullable();
            $table->unsignedInteger('registros')->default(0);
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'estado', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('envios_excel');
        Schema::dropIfExists('ajustes_envio_excel');
    }
};
