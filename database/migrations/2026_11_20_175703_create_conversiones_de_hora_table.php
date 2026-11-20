<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Constancia de la conversión única de las fechas guardadas en UTC a hora de Colombia,
     * para que el comando no se pueda ejecutar dos veces por error.
     */
    public function up(): void
    {
        Schema::create('conversiones_de_hora', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100)->unique();
            $table->smallInteger('horas');
            $table->unsignedInteger('columnas');
            $table->unsignedBigInteger('filas');
            $table->timestamp('ejecutada_en')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversiones_de_hora');
    }
};
