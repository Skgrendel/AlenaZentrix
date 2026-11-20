<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ajustes_revision_ia', function (Blueprint $table) {
            $table->boolean('revision_automatica')->default(false)->after('detalle_imagen');
        });
    }

    public function down(): void
    {
        Schema::table('ajustes_revision_ia', function (Blueprint $table) {
            $table->dropColumn('revision_automatica');
        });
    }
};
