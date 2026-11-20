<?php

namespace App\Livewire\Coordinador\SurveyAuditoria;

use App\Models\RevisionFotosIA;
use App\Models\SurveyAdjunto;
use App\Models\SurveyRegistro;
use App\Services\survey123\RequisitosDeFotos;
use App\Services\survey123\RevisorFotosIA;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

#[Layout('layouts.app')]
#[Title('Detalle del registro')]
/**
 * Detalle de un registro de suspensión: datos reportados, hallazgos de la auditoría y fotos,
 * con una lista de verificación de las fotos requeridas.
 */
class SurveyRegistroShow extends Component
{
    public SurveyRegistro $registro;

    public string $comentarioEvaluacion = '';

    /** Orden en que se muestran los tipos de foto. */
    private const ORDEN_DE_FOTOS = [
        'foto_fachada', 'foto_medidor', 'foto_ivt',
        'foto_actividad', 'foto_actividad1', 'foto_actividad2', 'foto_actividad3',
    ];

    public function mount(SurveyRegistro $registro): void
    {
        $this->registro = $registro;
    }

    /** Pide a la IA una revisión de las fotos y la guarda como sugerencia; no cambia la auditoría. */
    public function revisarConIA(RevisorFotosIA $revisor): void
    {
        abort_unless(auth()->user()?->can('ordenes.revisar'), 403);

        try {
            $revisor->revisarYGuardar($this->registro, auth()->id());
        } catch (Throwable $e) {
            Flux::toast(heading: 'No se pudo revisar con IA', text: $e->getMessage(), variant: 'danger');

            return;
        }

        Flux::toast(text: 'Revisión con IA guardada.', variant: 'success');
    }

    /** Guarda lo que opina una persona de la última revisión de la IA: de acuerdo o en desacuerdo. */
    public function evaluarRevisionIA(string $evaluacion): void
    {
        abort_unless(auth()->user()?->can('ordenes.revisar'), 403);

        $this->validate(['comentarioEvaluacion' => ['nullable', 'string', 'max:1000']]);

        if (! in_array($evaluacion, [RevisionFotosIA::DE_ACUERDO, RevisionFotosIA::EN_DESACUERDO], true)) {
            return;
        }

        $revision = $this->registro->revisionesIA()->latest()->first();

        if ($revision === null) {
            return;
        }

        $revision->update([
            'evaluacion' => $evaluacion,
            'comentario_evaluacion' => filled($this->comentarioEvaluacion) ? trim($this->comentarioEvaluacion) : null,
            'evaluado_por' => auth()->id(),
            'evaluado_en' => now(),
        ]);

        $this->comentarioEvaluacion = '';

        Flux::toast(text: 'Tu opinión quedó guardada.', variant: 'success');
    }

    /** Quita la opinión para poder darla de nuevo. */
    public function reabrirEvaluacionIA(): void
    {
        abort_unless(auth()->user()?->can('ordenes.revisar'), 403);

        $this->registro->revisionesIA()->latest()->first()?->update([
            'evaluacion' => null, 'comentario_evaluacion' => null, 'evaluado_por' => null, 'evaluado_en' => null,
        ]);
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
            'revisionIA' => $registro->revisionesIA()->with(['usuario.personal', 'evaluador.personal'])->latest()->first(),
            'puedeRevisarConIA' => auth()->user()?->can('ordenes.revisar') ?? false,
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
