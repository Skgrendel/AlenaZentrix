<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lo que una persona opina de cada revisión de la IA: de acuerdo o en desacuerdo, con un comentario opcional.
     * Sirve para medir qué tan confiable es la IA.
     */
    public function up(): void
    {
        Schema::table('revisiones_ia_survey', function (Blueprint $table) {
            $table->string('evaluacion', 20)->nullable()->after('con_observaciones');
            $table->text('comentario_evaluacion')->nullable()->after('evaluacion');
            $table->foreignUuid('evaluado_por')->nullable()->after('comentario_evaluacion')->constrained('users')->nullOnDelete();
            $table->timestamp('evaluado_en')->nullable()->after('evaluado_por');
            $table->index('evaluacion');
        });
    }

    public function down(): void
    {
        Schema::table('revisiones_ia_survey', function (Blueprint $table) {
            $table->dropIndex(['evaluacion']);
            $table->dropConstrainedForeignId('evaluado_por');
            $table->dropColumn(['evaluacion', 'comentario_evaluacion', 'evaluado_en']);
        });
    }
};
