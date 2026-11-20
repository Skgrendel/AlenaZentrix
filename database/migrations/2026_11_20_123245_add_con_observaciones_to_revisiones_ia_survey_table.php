<?php

use App\Models\RevisionFotosIA;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('revisiones_ia_survey', function (Blueprint $table) {
            $table->boolean('con_observaciones')->default(false)->after('veredicto');
            $table->index('con_observaciones');
        });

        // Las revisiones guardadas antes de esta columna también deben quedar marcadas.
        DB::table('revisiones_ia_survey')->orderBy('id')->each(function (object $revision): void {
            DB::table('revisiones_ia_survey')->where('id', $revision->id)->update([
                'con_observaciones' => RevisionFotosIA::hayObservaciones((array) json_decode($revision->veredicto, true)),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('revisiones_ia_survey', function (Blueprint $table) {
            $table->dropIndex(['con_observaciones']);
            $table->dropColumn('con_observaciones');
        });
    }
};
