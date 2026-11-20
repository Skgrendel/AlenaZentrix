<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('survey_registros', function (Blueprint $table) {
            $table->json('hallazgos')->nullable()->after('estado');
            $table->unsignedSmallInteger('total_errores')->default(0)->after('hallazgos');
            $table->unsignedSmallInteger('total_alertas')->default(0)->after('total_errores');
            $table->dateTime('auditado_en')->nullable()->after('total_alertas');
        });
    }

    public function down(): void
    {
        Schema::table('survey_registros', function (Blueprint $table) {
            $table->dropColumn(['hallazgos', 'total_errores', 'total_alertas', 'auditado_en']);
        });
    }
};
