<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reportes', function (Blueprint $table) {
            $table->json('comparacion')->nullable();
            $table->string('resultado_comparacion', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('reportes', function (Blueprint $table) {
            $table->dropColumn(['comparacion', 'resultado_comparacion']);
        });
    }
};
