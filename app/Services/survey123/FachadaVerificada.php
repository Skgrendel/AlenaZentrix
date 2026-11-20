<?php

namespace App\Services\survey123;

use App\Models\SurveyAdjunto;
use App\Models\SurveyRegistro;

/**
 * Foto de fachada que se le puede mostrar al auditor de campo sin romper la auditoría ciega.
 *
 * Los técnicos a veces suben en el campo de fachada un primer plano del medidor, con la lectura a la vista.
 * Por eso solo se entrega una foto que la última revisión con IA confirmó como fachada; sin revisión
 * (o sin confirmación) no se muestra ninguna.
 */
class FachadaVerificada
{
    public function para(SurveyRegistro $registro): ?SurveyAdjunto
    {
        $veredicto = $registro->revisionesIA()->latest()->value('veredicto');

        if (! is_array($veredicto)) {
            return null;
        }

        $mapa = $veredicto['mapa_de_fotos'] ?? [];

        $confirmadas = collect($veredicto['fotos'] ?? [])
            ->filter(fn (array $foto): bool => ($foto['muestra'] ?? null) === 'fachada'
                && ($foto['campo_declarado'] ?? null) === 'foto_fachada'
                && ($foto['coincide_con_campo'] ?? false) === true
                && ($foto['calidad'] ?? null) !== 'mala')
            ->map(fn (array $foto) => $mapa[$foto['numero'] ?? 0] ?? null)
            ->filter()
            ->values();

        if ($confirmadas->isEmpty()) {
            return null;
        }

        return $registro->adjuntos()
            ->whereIn('id', $confirmadas)
            ->where('campo', 'foto_fachada')
            ->whereNotNull('ruta')
            ->orderBy('attachment_id')
            ->first();
    }
}
