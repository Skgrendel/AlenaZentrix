<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orden_jobs', function (Blueprint $table) {
            $table->foreignUuid('survey_registro_id')
                ->nullable()
                ->after('origen_informacion_id')
                ->constrained('survey_registros')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orden_jobs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('survey_registro_id');
        });
    }
};
