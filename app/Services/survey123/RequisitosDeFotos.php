<?php

namespace App\Services\survey123;

use App\Models\SurveyRegistro;
use Illuminate\Support\Carbon;

/**
 * Qué fotos debe traer un registro de suspensión, según la versión del formulario con que se envió:
 *
 * - Formulario nuevo: fachada, medidor, IVT y tres de la actividad (antes, durante y después).
 * - Formulario anterior: fachada, medidor y una sola foto de actividad (`foto_actividad`).
 *
 * Un registro es del formulario nuevo si trae alguna de las fotos nuevas o si se registró después del
 * cambio sin ninguna foto de actividad. Uno posterior al cambio que solo trae `foto_actividad` se envió
 * con una versión vieja guardada en el teléfono: se señala con una alerta, sin exigirle lo que no pudo enviar.
 */
class RequisitosDeFotos
{
    public const ACTIVIDAD_ANTERIOR = 'foto_actividad';

    /** Fotos del formulario nuevo: nombre de la pregunta => [etiqueta, mensaje cuando falta]. */
    public const FOTOS_NUEVAS = [
        'foto_ivt' => ['IVT', 'Falta la foto del IVT.'],
        'foto_actividad1' => ['Actividad · antes', 'Falta la foto de la actividad (antes).'],
        'foto_actividad2' => ['Actividad · durante', 'Falta la foto de la actividad (durante).'],
        'foto_actividad3' => ['Actividad · después', 'Falta la foto de la actividad (después).'],
    ];

    /** Todos los nombres de foto de la actividad, de ambas versiones del formulario. */
    public const CAMPOS_ACTIVIDAD = ['foto_actividad', 'foto_actividad1', 'foto_actividad2', 'foto_actividad3'];

    /**
     * @return array{
     *     requisitos: array<int, array{campo: string, etiqueta: string, requeridas: int, subidas: int, completa: bool, aplica: bool, mensaje: string}>,
     *     formulario_nuevo: bool,
     *     version_anterior: bool
     * }
     */
    public function evaluar(SurveyRegistro $registro): array
    {
        $registro->loadMissing('adjuntos');

        $conteo = $registro->adjuntos->groupBy('campo')->map->count();
        $cuantas = fn (string $campo): int => (int) ($conteo[$campo] ?? 0);

        $tieneNuevas = collect(array_keys(self::FOTOS_NUEVAS))->contains(fn (string $campo): bool => $cuantas($campo) > 0);
        $tieneAnterior = $cuantas(self::ACTIVIDAD_ANTERIOR) > 0;
        $posteriorAlCambio = $registro->fecha_registro !== null
            && $registro->fecha_registro->gte(Carbon::parse(config('survey_auditoria.parametros.formulario_nuevo_desde'), 'UTC'));

        $formularioNuevo = $tieneNuevas || ($posteriorAlCambio && ! $tieneAnterior);
        $versionAnterior = ! $tieneNuevas && $tieneAnterior && $posteriorAlCambio;
        $ejecutada = $registro->suspension_ejecutada === true;

        $requisitos = [];

        foreach (config('survey_auditoria.parametros.fotos_minimas') as $campo => $minimo) {
            $requisitos[] = $this->conCantidad($campo, $minimo, $cuantas($campo), $campo === 'foto_fachada' || $ejecutada);
        }

        if ($formularioNuevo) {
            foreach (self::FOTOS_NUEVAS as $campo => [$etiqueta, $mensaje]) {
                $subidas = $cuantas($campo);
                $requisitos[] = [
                    'campo' => $campo, 'etiqueta' => $etiqueta, 'requeridas' => 1, 'subidas' => $subidas,
                    'completa' => $subidas >= 1, 'aplica' => $ejecutada, 'mensaje' => $mensaje,
                ];
            }
        } else {
            $requisitos[] = $this->conCantidad(
                self::ACTIVIDAD_ANTERIOR,
                (int) config('survey_auditoria.parametros.foto_actividad_anterior_minimo'),
                $cuantas(self::ACTIVIDAD_ANTERIOR),
                $ejecutada,
            );
        }

        return ['requisitos' => $requisitos, 'formulario_nuevo' => $formularioNuevo, 'version_anterior' => $versionAnterior];
    }

    public function etiqueta(string $campo): string
    {
        return match (true) {
            $campo === 'foto_fachada' => 'Fachada',
            $campo === 'foto_medidor' => 'Medidor',
            $campo === self::ACTIVIDAD_ANTERIOR => 'Actividad (suspensión o novedad)',
            isset(self::FOTOS_NUEVAS[$campo]) => self::FOTOS_NUEVAS[$campo][0],
            default => 'Otras fotos',
        };
    }

    /** @return array{campo: string, etiqueta: string, requeridas: int, subidas: int, completa: bool, aplica: bool, mensaje: string} */
    private function conCantidad(string $campo, int $requeridas, int $subidas, bool $aplica): array
    {
        $nombre = $campo === self::ACTIVIDAD_ANTERIOR ? 'actividad' : str_replace('foto_', '', $campo);

        return [
            'campo' => $campo,
            'etiqueta' => $this->etiqueta($campo),
            'requeridas' => $requeridas,
            'subidas' => $subidas,
            'completa' => $subidas >= $requeridas,
            'aplica' => $aplica,
            'mensaje' => "Hay {$subidas} foto(s) de {$nombre} y se requieren al menos {$requeridas}.",
        ];
    }
}
