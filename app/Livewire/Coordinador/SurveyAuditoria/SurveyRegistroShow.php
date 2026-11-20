<?php

namespace App\Livewire\Coordinador\SurveyAuditoria;

use App\Models\SurveyAdjunto;
use App\Models\SurveyRegistro;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Detalle del registro')]
/**
 * Detalle de un registro de Survey123: datos reportados, hallazgos de la auditoría y fotos,
 * con una lista de verificación de las fotos requeridas.
 */
class SurveyRegistroShow extends Component
{
    public SurveyRegistro $registro;

    /** @var array<string, string> */
    private const ETIQUETAS_FOTO = [
        'foto_fachada' => 'Fachada',
        'foto_medidor' => 'Medidor',
        'foto_actividad' => 'Actividad (suspensión o novedad)',
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

    /** @return array<int, array{campo: string, etiqueta: string, requeridas: int, subidas: int, completa: bool, aplica: bool}> */
    private function listaDeVerificacion(SurveyRegistro $registro): array
    {
        $conteo = $registro->adjuntos->groupBy('campo')->map->count();

        return collect(config('survey_auditoria.parametros.fotos_minimas'))
            ->map(function (int $minimo, string $campo) use ($conteo, $registro): array {
                $subidas = (int) ($conteo[$campo] ?? 0);

                return [
                    'campo' => $campo,
                    'etiqueta' => self::ETIQUETAS_FOTO[$campo] ?? $campo,
                    'requeridas' => $minimo,
                    'subidas' => $subidas,
                    'completa' => $subidas >= $minimo,
                    'aplica' => $campo === 'foto_fachada' || $registro->suspension_ejecutada === true,
                ];
            })
            ->values()
            ->all();
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
            ->groupBy(fn (SurveyAdjunto $a): string => $a->campo ?? 'otros');
    }

    public function etiquetaFoto(string $campo): string
    {
        return self::ETIQUETAS_FOTO[$campo] ?? 'Otras fotos';
    }
}
