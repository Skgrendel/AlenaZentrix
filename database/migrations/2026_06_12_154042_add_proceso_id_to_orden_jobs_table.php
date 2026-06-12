<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orden_jobs', function (Blueprint $table) {
            $table->foreignUuid('proceso_id')
                ->nullable()
                ->after('company_id')
                ->constrained('procesos')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orden_jobs', function (Blueprint $table) {
            $table->dropForeign(['proceso_id']);
        });

        Schema::table('orden_jobs', function (Blueprint $table) {
            $table->dropColumn('proceso_id');
        });
    }
};
