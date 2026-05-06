<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comercios', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nombre_comercio')->nullable();
            $table->uuid('tipo_comercio_id')->nullable();
            $table->string('medidor_anomalia', 125)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comercios');
    }
};
