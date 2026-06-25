<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auditorias', function (Blueprint $table) {
            $table->foreignUuid('tipo_anomalia_id')->nullable()->constrained('tipos_anomalia')->nullOnDelete()->after('observaciones');
            $table->decimal('metros_cubicos_recuperados', 10, 4)->nullable()->after('tipo_anomalia_id');
        });
    }

    public function down(): void
    {
        Schema::table('auditorias', function (Blueprint $table) {
            $table->dropForeign(['tipo_anomalia_id']);
            $table->dropColumn(['tipo_anomalia_id', 'metros_cubicos_recuperados']);
        });
    }
};
