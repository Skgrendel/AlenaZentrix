<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Map of existing slug => human name expected in tipologias_orden.nombre
        $mapping = [
            'capturas' => 'Capturas Criminales',
            'bandas' => 'Desarticulación de Bandas',
            'querella' => 'Querella Penal',
            'seguimiento_denuncia' => 'Seguimiento a Denuncia',
            'diligencia_judicial' => 'Diligencia Judicial',
        ];

        // Default company id: first company in DB. Skip if none exist (e.g. fresh test database).
        $defaultCompanyId = DB::table('companies')->value('id');

        if (! $defaultCompanyId) {
            return;
        }

        foreach ($mapping as $slug => $nombre) {
            $tip = DB::table('tipologias_orden')->where('nombre', $nombre)->first();

            if (! $tip) {
                $id = (string) Str::uuid();

                DB::table('tipologias_orden')->insert([
                    'id' => $id,
                    'nombre' => $nombre,
                    'activo' => true,
                    'company_id' => $defaultCompanyId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $tipId = $id;
            } else {
                $tipId = $tip->id;
            }

            // Update orden_jobs: replace slug with the tipologia id
            DB::table('orden_jobs')
                ->where('tipo_gestion_legal', $slug)
                ->update(['tipo_gestion_legal' => $tipId]);
        }
    }

    public function down(): void
    {
        // Revert: attempt to map tipologia ids back to slugs for the known mapping
        $mapping = [
            'capturas' => 'Capturas Criminales',
            'bandas' => 'Desarticulación de Bandas',
            'querella' => 'Querella Penal',
            'seguimiento_denuncia' => 'Seguimiento a Denuncia',
            'diligencia_judicial' => 'Diligencia Judicial',
        ];

        foreach ($mapping as $slug => $nombre) {
            $tip = DB::table('tipologias_orden')->where('nombre', $nombre)->first();
            if (! $tip) {
                continue;
            }

            // Only convert back values that match the tipologia id
            DB::table('orden_jobs')
                ->where('tipo_gestion_legal', $tip->id)
                ->update(['tipo_gestion_legal' => $slug]);
        }
    }
};
