<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Datos de referencia reportados por el técnico original (Survey123) para contrastarlos
     * con lo que encuentra el auditor en campo.
     */
    public function up(): void
    {
        Schema::table('orden_jobs', function (Blueprint $table) {
            $table->string('serie_medidor_referencia', 50)->nullable()->after('lectura_referencia');
            $table->decimal('latitud_referencia', 10, 7)->nullable()->after('serie_medidor_referencia');
            $table->decimal('longitud_referencia', 10, 7)->nullable()->after('latitud_referencia');
        });
    }

    public function down(): void
    {
        Schema::table('orden_jobs', function (Blueprint $table) {
            $table->dropColumn(['serie_medidor_referencia', 'latitud_referencia', 'longitud_referencia']);
        });
    }
};
