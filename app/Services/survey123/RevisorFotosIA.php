<?php

namespace App\Services\survey123;

use App\Models\AjusteRevisionIA;
use App\Models\RevisionFotosIA;
use App\Models\SurveyAdjunto;
use App\Models\SurveyRegistro;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Pide a un modelo con visión que revise las fotos de un registro. Solo emite un veredicto:
 * no modifica la auditoría (modo sombra, para que una persona compare y decida).
 */
class RevisorFotosIA
{
    private const LADO_MAXIMO = 1024;

    public const INSTRUCCIONES_RECOMENDADAS = <<<'TXT'
Eres auditor de calidad de suspensiones del servicio de gas natural en Colombia (Surtigas). Recibes las fotos que un técnico
tomó al suspender el servicio, cada una con el campo del formulario en que la subió, y los datos que el técnico reportó.

QUÉ ES CADA FOTO
- foto_fachada: el frente del predio visto desde la calle o la puerta. Un primer plano del medidor, de la tubería o de un documento NO es una fachada.
- foto_medidor: el medidor de cerca, con la serie y la lectura legibles.
- foto_ivt: el Informe de Visita Técnica (formato impreso F-08-12 de Surtigas, en una tabla con portapapeles).
- foto_actividad, foto_actividad1/2/3: la actividad de suspensión (antes, durante, después). Se ve al técnico trabajando o el resultado:
  un tapón o dispositivo captor en la acometida, a veces asegurado con pinzas, o la sonda CTS instalada en el medidor.

QUÉ BUSCAR
1. Para cada foto, qué muestra realmente y si corresponde al campo en que se subió. Fotos puestas en el campo equivocado (por ejemplo, un IVT en el campo de actividad) son un hallazgo.
2. Fotos repetidas o casi iguales entre campos distintos.
3. Cada foto trae un sello con fecha, hora, contrato y dirección. Lee el contrato del sello y compáralo con el contrato reportado; la fecha del sello debe ser la de la visita.
4. En la foto del medidor lee la serie y la lectura (solo los dígitos antes del recuadro rojo) y compáralas con lo reportado. Si no se alcanzan a leer, dilo en lugar de adivinar.
5. En el IVT lee el número de OT y el contrato y compáralos con el registro.
6. Si la suspensión se reportó como ejecutada, indica si en alguna foto se ve la suspensión (tapón, dispositivo captor, sonda).

REGLAS
- Si no puedes asegurar algo, responde null o dilo en la observación. No inventes.
- Usa severidad "error" solo para lo que evidencia un problema real (dato que no coincide, foto en el campo equivocado, foto repetida), "alerta" para dudas de calidad o legibilidad, e "info" para el resto.
- Responde en español y solo con el JSON pedido.
TXT;

    /**
     * @return array{veredicto: array<string, mixed>, tokens_entrada: int, tokens_salida: int, costo_usd: float|null, fotos: int, modelo: string}
     */
    public function revisar(SurveyRegistro $registro, ?AjusteRevisionIA $ajuste = null): array
    {
        $clave = config('services.openai.api_key');

        if (blank($clave)) {
            throw new RuntimeException('Falta OPENAI_API_KEY en la configuración.');
        }

        $ajuste ??= AjusteRevisionIA::de($registro->company_id);

        $adjuntos = $registro->adjuntos()->orderBy('campo')->orderBy('nombre')->get();

        if ($adjuntos->isEmpty()) {
            throw new RuntimeException('El registro no tiene fotos para revisar.');
        }

        $mapaDeFotos = [];
        $contenido = $this->contenido($registro, $adjuntos, $ajuste->detalle_imagen, $mapaDeFotos);

        $respuesta = Http::withToken($clave)
            ->timeout(120)
            ->acceptJson()
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $ajuste->modelo,
                'messages' => [
                    ['role' => 'system', 'content' => $ajuste->instrucciones],
                    ['role' => 'user', 'content' => $contenido],
                ],
                'response_format' => ['type' => 'json_schema', 'json_schema' => $this->esquema()],
                'max_completion_tokens' => 2000,
            ]);

        if ($respuesta->failed()) {
            throw new RuntimeException('OpenAI respondió '.$respuesta->status().': '.($respuesta->json('error.message') ?? $respuesta->body()));
        }

        $veredicto = json_decode((string) $respuesta->json('choices.0.message.content'), true);

        if (! is_array($veredicto)) {
            throw new RuntimeException('La respuesta de la IA no tiene el formato esperado.');
        }

        // Vincula cada número de foto del veredicto con la foto real, para poder usar el veredicto después.
        $veredicto['mapa_de_fotos'] = $mapaDeFotos;

        $entrada = (int) $respuesta->json('usage.prompt_tokens');
        $salida = (int) $respuesta->json('usage.completion_tokens');

        return [
            'veredicto' => $veredicto,
            'tokens_entrada' => $entrada,
            'tokens_salida' => $salida,
            'costo_usd' => self::costo($ajuste->modelo, $entrada, $salida),
            'fotos' => $adjuntos->count(),
            'modelo' => $ajuste->modelo,
        ];
    }

    /** Revisa el registro y guarda el veredicto como una nueva revisión (el historial se conserva). */
    public function revisarYGuardar(SurveyRegistro $registro, ?string $usuarioId = null): RevisionFotosIA
    {
        $ajuste = AjusteRevisionIA::de($registro->company_id);
        $r = $this->revisar($registro, $ajuste);

        return $registro->revisionesIA()->create([
            'modelo' => $r['modelo'],
            'detalle_imagen' => $ajuste->detalle_imagen,
            'instrucciones_hash' => sha1($ajuste->instrucciones),
            'veredicto' => $r['veredicto'],
            'fotos' => $r['fotos'],
            'tokens_entrada' => $r['tokens_entrada'],
            'tokens_salida' => $r['tokens_salida'],
            'costo_usd' => $r['costo_usd'],
            'user_id' => $usuarioId,
        ]);
    }

    /** Costo estimado en USD, o null si el modelo no tiene tarifa configurada. */
    public static function costo(string $modelo, int $entrada, int $salida): ?float
    {
        $tarifa = config('services.openai.modelos')[$modelo] ?? null;

        if (! is_array($tarifa)) {
            return null;
        }

        return $entrada / 1_000_000 * $tarifa['entrada'] + $salida / 1_000_000 * $tarifa['salida'];
    }

    /**
     * @param  iterable<int, SurveyAdjunto>  $adjuntos
     * @param  array<int, string>  $mapaDeFotos  se llena con número de foto => id del adjunto
     * @return array<int, array<string, mixed>>
     */
    private function contenido(SurveyRegistro $registro, iterable $adjuntos, string $detalle, array &$mapaDeFotos): array
    {
        $partes = [[
            'type' => 'text',
            'text' => implode("\n", [
                'Datos reportados por el técnico:',
                "- OT: {$registro->orden_trabajo}",
                "- Contrato: {$registro->contrato}",
                "- Serie del medidor: {$registro->serie_medidor}",
                "- Lectura: {$registro->lectura_medidor}",
                '- Suspensión ejecutada: '.($registro->suspension_ejecutada ? 'sí' : 'no'),
                "- Método: {$registro->metodo_suspension}",
                '- Fecha del registro: '.$registro->fecha_registro?->timezone(config('survey_auditoria.zona_horaria_excel'))->format('d/m/Y H:i'),
            ]),
        ]];

        $disco = Storage::disk(config('services.survey123.disco_fotos'));
        $numero = 0;

        foreach ($adjuntos as $adjunto) {
            $contenido = $disco->get($adjunto->ruta);

            if ($contenido === null || $contenido === false) {
                continue;
            }

            $numero++;
            $mapaDeFotos[$numero] = $adjunto->id;
            $partes[] = ['type' => 'text', 'text' => "Foto {$numero} — campo declarado: {$adjunto->campo} — archivo: {$adjunto->nombre}"];
            $partes[] = ['type' => 'image_url', 'image_url' => [
                'url' => 'data:image/jpeg;base64,'.base64_encode($this->reducir($contenido)),
                'detail' => $detalle,
            ]];
        }

        return $partes;
    }

    private function reducir(string $contenido): string
    {
        if (! function_exists('imagecreatefromstring')) {
            return $contenido;
        }

        $origen = @imagecreatefromstring($contenido);

        if ($origen === false) {
            return $contenido;
        }

        $ancho = imagesx($origen);
        $alto = imagesy($origen);
        $factor = min(1, self::LADO_MAXIMO / max($ancho, $alto));
        $destino = imagescale($origen, max(1, (int) round($ancho * $factor)), max(1, (int) round($alto * $factor)));

        ob_start();
        imagejpeg($destino === false ? $origen : $destino, null, 80);

        return (string) ob_get_clean();
    }

    /** @return array<string, mixed> */
    private function esquema(): array
    {
        $texto = ['type' => 'string'];
        $textoONulo = ['type' => ['string', 'null']];

        return [
            'name' => 'revision_fotos',
            'strict' => true,
            'schema' => [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['fotos', 'fotos_repetidas', 'suspension_visible', 'datos_leidos', 'hallazgos', 'resumen'],
                'properties' => [
                    'fotos' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'required' => ['numero', 'campo_declarado', 'muestra', 'coincide_con_campo', 'calidad', 'observacion'],
                            'properties' => [
                                'numero' => ['type' => 'integer'],
                                'campo_declarado' => $texto,
                                'muestra' => ['type' => 'string', 'enum' => ['fachada', 'medidor', 'ivt', 'actividad', 'otro']],
                                'coincide_con_campo' => ['type' => 'boolean'],
                                'calidad' => ['type' => 'string', 'enum' => ['buena', 'regular', 'mala']],
                                'observacion' => $texto,
                            ],
                        ],
                    ],
                    'fotos_repetidas' => ['type' => 'boolean'],
                    'suspension_visible' => ['type' => ['boolean', 'null']],
                    'datos_leidos' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['contrato', 'serie_medidor', 'lectura_medidor', 'contrato_coincide', 'serie_coincide', 'lectura_coincide'],
                        'properties' => [
                            'contrato' => $textoONulo,
                            'serie_medidor' => $textoONulo,
                            'lectura_medidor' => $textoONulo,
                            'contrato_coincide' => ['type' => ['boolean', 'null']],
                            'serie_coincide' => ['type' => ['boolean', 'null']],
                            'lectura_coincide' => ['type' => ['boolean', 'null']],
                        ],
                    ],
                    'hallazgos' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'required' => ['severidad', 'detalle'],
                            'properties' => [
                                'severidad' => ['type' => 'string', 'enum' => ['error', 'alerta', 'info']],
                                'detalle' => $texto,
                            ],
                        ],
                    ],
                    'resumen' => $texto,
                ],
            ],
        ];
    }
}
