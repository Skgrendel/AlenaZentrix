<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orden_jobs', function (Blueprint $table) {
            $table->foreignUuid('origen_informacion_id')
                ->nullable()
                ->after('proceso_id')
                ->constrained('origenes_informacion')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orden_jobs', function (Blueprint $table) {
            $table->dropForeign(['origen_informacion_id']);
        });

        Schema::table('orden_jobs', function (Blueprint $table) {
            $table->dropColumn('origen_informacion_id');
        });
    }
};
