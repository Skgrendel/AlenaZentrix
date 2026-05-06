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
            $table->boolean('medidor_coincide')->nullable();
            $table->boolean('lectura_correcta')->nullable();
            $table->boolean('foto_correcta')->nullable();
            $table->boolean('comercio_coincide')->nullable();
            $table->boolean('intento_soborno')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditorias');
    }
};
