<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('auditorias')
            ->whereNotNull('metros_cubicos_recuperados')
            ->update(['metros_cubicos_recuperados' => DB::raw('ROUND(metros_cubicos_recuperados)')]);

        Schema::table('auditorias', function (Blueprint $table) {
            $table->unsignedInteger('metros_cubicos_recuperados')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('auditorias', function (Blueprint $table) {
            $table->decimal('metros_cubicos_recuperados', 10, 4)->nullable()->change();
        });
    }
};
