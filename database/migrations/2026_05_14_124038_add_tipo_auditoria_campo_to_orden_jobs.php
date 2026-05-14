<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orden_jobs', function (Blueprint $table) {
            $table->string('tipo_auditoria_campo')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orden_jobs', function (Blueprint $table) {
            $table->dropColumn('tipo_auditoria_campo');
        });
    }
};
