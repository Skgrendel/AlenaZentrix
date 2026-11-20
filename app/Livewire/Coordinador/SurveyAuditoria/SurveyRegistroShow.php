<?php

namespace App\Livewire\Coordinador\SurveyAuditoria;

use App\Models\SurveyAdjunto;
use App\Models\SurveyRegistro;
use App\Services\survey123\RequisitosDeFotos;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Detalle del registro')]
/**
 * Detalle de un registro de suspensión: datos reportados, hallazgos de la auditoría y fotos,
 * con una lista de verificación de las fotos requeridas.
 */
class SurveyRegistroShow extends Component
{
    public SurveyRegistro $registro;

    /** Orden en que se muestran los tipos de foto. */
    private const ORDEN_DE_FOTOS = [
        'foto_fachada', 'foto_medidor', 'foto_ivt',
        'foto_actividad', 'foto_actividad1', 'foto_actividad2', 'foto_actividad3',
    ];

    public function mount(SurveyRegistro $registro): void
    {
        $this->registro = $registro;
    }

    public function render(): View
    {
        $registro = $this->registro->load(['adjuntos', 'ordenes.reportes']);

        return view('livewire.coordinador.survey-auditoria.survey-registro-show', [
            'registro' => $registro,
            'metodo' => SurveyRegistro::METODOS[$registro->metodo_suspension] ?? $registro->metodo_suspension,
            'motivo' => SurveyRegistro::MOTIVOS[$registro->motivo_no_ejecucion] ?? $registro->motivo_no_ejecucion,
            'hallazgos' => collect($registro->hallazgos ?? [])->sortBy(fn (array $h): int => $h['severidad'] === 'error' ? 0 : 1),
            'listaFotos' => $this->listaDeVerificacion($registro),
            'grupos' => $this->gruposDeFotos($registro),
            'orden' => $registro->ordenes->sortByDesc('created_at')->first(),
        ]);
    }

    /** @return array<int, array{campo: string, etiqueta: string, requeridas: int, subidas: int, completa: bool, aplica: bool, mensaje: string}> */
    private function listaDeVerificacion(SurveyRegistro $registro): array
    {
        return app(RequisitosDeFotos::class)->evaluar($registro)['requisitos'];
    }

    /**
     * Fotos agrupadas por tipo, marcando las repetidas dentro del registro y las usadas en otras visitas.
     *
     * @return Collection<string, Collection<int, SurveyAdjunto>>
     */
    private function gruposDeFotos(SurveyRegistro $registro): Collection
    {
        $repetidasAqui = $registro->adjuntos->whereNotNull('hash')->groupBy('hash')->filter(fn ($g) => $g->count() > 1)->keys();

        $usadasEnOtros = SurveyAdjunto::query()
            ->whereIn('hash', $registro->adjuntos->pluck('hash')->filter())
            ->where('survey_registro_id', '!=', $registro->id)
            ->pluck('hash')
            ->unique();

        return $registro->adjuntos
            ->sortBy('attachment_id')
            ->each(function (SurveyAdjunto $a) use ($repetidasAqui, $usadasEnOtros): void {
                $a->setAttribute('repetida_aqui', $repetidasAqui->contains($a->hash));
                $a->setAttribute('usada_en_otra_visita', $usadasEnOtros->contains($a->hash));
            })
            ->groupBy(fn (SurveyAdjunto $a): string => $a->campo ?? 'otros')
            ->sortBy(function ($grupo, string $campo): int {
                $posicion = array_search($campo, self::ORDEN_DE_FOTOS, true);

                return $posicion === false ? count(self::ORDEN_DE_FOTOS) : $posicion;
            });
    }

    public function etiquetaFoto(string $campo): string
    {
        return app(RequisitosDeFotos::class)->etiqueta($campo);
    }
}
