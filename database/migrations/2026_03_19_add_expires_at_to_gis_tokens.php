<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Agregar columna expires_at si no existe
        if (Schema::hasTable('gis_tokens') && !Schema::hasColumn('gis_tokens', 'expires_at')) {
            Schema::table('gis_tokens', function (Blueprint $table) {
                $table->timestamp('expires_at')->nullable()->after('activo');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('gis_tokens') && Schema::hasColumn('gis_tokens', 'expires_at')) {
            Schema::table('gis_tokens', function (Blueprint $table) {
                $table->dropColumn('expires_at');
            });
        }
    }
};
