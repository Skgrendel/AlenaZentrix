<?php

namespace App\Services\survey123;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Traduce los atributos de un registro de Survey123 a las columnas de survey_registros.
 * Localiza cada campo por su alias (la pregunta del formulario) y convierte las opciones
 * a los mismos códigos que usa el formulario del auditor.
 */
class Survey123Mapeador
{
    /** @var array<string, string> columna destino => alias de la pregunta */
    private const ALIAS = [
        'fecha_registro' => 'Fecha de registro',
        'tecnico' => 'Nombre del Técnico',
        'contrato' => 'Contrato',
        'orden_trabajo' => 'Orden de Trabajo',
        'categoria' => 'Categoría en campo',
        'serie_medidor' => 'Serie de medidor instalado',
        'lectura_medidor' => 'Lectura del medidor instalado',
        'actividad' => '¿Qué actividad va a ejecutar?',
        'suspension_ejecutada' => '¿Se pudo ejecutar la suspensión?',
        'metodo_suspension' => '¿Método de suspensión instalado?',
        'serie_sonda' => 'Serie de la sonda utilizada',
        'serie_caps' => 'Serie del Caps',
        'motivo_no_ejecucion' => 'Motivo por el cual no se pudo ejecutar la suspensión',
        'justificacion' => 'JUSTIFICACIÓN',
    ];

    /** @var array<string, string> columna destino => nombre real del campo en el servicio */
    private array $campos = [];

    /** @var array<string, array<int|string, string>> nombre del campo => código => etiqueta */
    private array $dominios = [];

    private ?string $campoId = null;

    private ?string $campoGlobalId = null;

    private ?string $campoEdicion = null;

    private ?string $campoCreacion = null;

    /** @param array<string, mixed> $metadatos respuesta del servicio con `fields`. */
    public function __construct(array $metadatos)
    {
        $porAlias = [];

        foreach ($metadatos['fields'] ?? [] as $campo) {
            $porAlias[self::normalizar($campo['alias'] ?? $campo['name'])][] = $campo['name'];

            foreach ($campo['domain']['codedValues'] ?? [] as $valor) {
                $this->dominios[$campo['name']][$valor['code']] = $valor['name'];
            }
        }

        foreach (self::ALIAS as $columna => $alias) {
            // Si dos preguntas comparten alias (sonda/Caps de reconexión y de suspensión),
            // la de suspensión es la última del formulario.
            $nombres = $porAlias[self::normalizar($alias)] ?? [];

            if ($nombres !== []) {
                $this->campos[$columna] = end($nombres);
            }
        }

        $this->campoId = $metadatos['objectIdField'] ?? 'OBJECTID';
        $this->campoGlobalId = $metadatos['globalIdField'] ?? 'GlobalID';
        $this->campoEdicion = $metadatos['editFieldsInfo']['editDateField'] ?? 'EditDate';
        $this->campoCreacion = $metadatos['editFieldsInfo']['creationDateField'] ?? 'CreationDate';
    }

    public function campoId(): string
    {
        return $this->campoId;
    }

    public function campoEdicion(): string
    {
        return $this->campoEdicion;
    }

    /**
     * @param  array<string, mixed>  $feature
     * @return array<string, mixed>
     */
    public function mapear(array $feature): array
    {
        $atributos = $feature['attributes'] ?? [];
        $valor = fn (string $columna): mixed => isset($this->campos[$columna])
            ? ($atributos[$this->campos[$columna]] ?? null)
            : null;
        $etiqueta = fn (string $columna): ?string => $this->etiqueta($columna, $valor($columna));

        $geometria = $feature['geometry'] ?? [];

        return [
            'object_id' => $atributos[$this->campoId] ?? null,
            'global_id' => $atributos[$this->campoGlobalId] ?? null,
            'fecha_registro' => $this->fecha($valor('fecha_registro')),
            'tecnico' => $this->texto($valor('tecnico')),
            'contrato' => $this->numeroComoTexto($valor('contrato')),
            'orden_trabajo' => $this->numeroComoTexto($valor('orden_trabajo')),
            'categoria' => $this->categoria($etiqueta('categoria')),
            'serie_medidor' => $this->numeroComoTexto($valor('serie_medidor')),
            'lectura_medidor' => $this->numeroComoTexto($valor('lectura_medidor')),
            'actividad' => Str::lower(Str::ascii((string) $etiqueta('actividad'))) ?: null,
            'suspension_ejecutada' => $this->booleano($etiqueta('suspension_ejecutada')),
            'metodo_suspension' => $this->metodo($etiqueta('metodo_suspension'), $valor('metodo_suspension')),
            'serie_sonda' => $this->numeroComoTexto($valor('serie_sonda')),
            'serie_caps' => $this->numeroComoTexto($valor('serie_caps')),
            'motivo_no_ejecucion' => $this->motivo($etiqueta('motivo_no_ejecucion')),
            'justificacion' => $this->texto($valor('justificacion')),
            'latitud' => $geometria['y'] ?? null,
            'longitud' => $geometria['x'] ?? null,
            'creado_en_origen' => $this->fecha($atributos[$this->campoCreacion] ?? null),
            'editado_en_origen' => $this->fecha($atributos[$this->campoEdicion] ?? null),
            'payload' => $atributos,
        ];
    }

    public static function normalizar(string $texto): string
    {
        return preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii($texto))) ?? '';
    }

    private function etiqueta(string $columna, mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        $campo = $this->campos[$columna] ?? null;

        return (string) ($this->dominios[$campo][$valor] ?? $valor);
    }

    private function texto(mixed $valor): ?string
    {
        $texto = trim((string) $valor);

        return $texto === '' ? null : $texto;
    }

    private function numeroComoTexto(mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        if (is_float($valor) && floor($valor) === $valor) {
            return (string) (int) $valor;
        }

        return (string) $valor;
    }

    private function fecha(mixed $valor): ?Carbon
    {
        return is_numeric($valor) ? Carbon::createFromTimestampMs((int) $valor) : null;
    }

    private function booleano(?string $valor): ?bool
    {
        return match (self::normalizar((string) $valor)) {
            'si', 'yes', 'true', '1' => true,
            'no', 'false', '0' => false,
            default => null,
        };
    }

    private function categoria(?string $etiqueta): ?string
    {
        return $etiqueta === null ? null : Str::lower(Str::ascii($etiqueta));
    }

    private function metodo(?string $etiqueta, mixed $codigo): ?string
    {
        $clave = self::normalizar((string) $etiqueta);

        return match (true) {
            $clave === '' => null,
            str_contains($clave, 'ips') => 'sonda_ips',
            str_contains($clave, 'cts') => 'sonda_cts',
            str_contains($clave, 'multifamiliar') => 'taponamiento_multifamiliar',
            str_contains($clave, 'acometida') => 'taponamiento_acometida',
            default => (string) $codigo,
        };
    }

    private function motivo(?string $etiqueta): ?string
    {
        $clave = self::normalizar((string) $etiqueta);

        return match (true) {
            $clave === '' => null,
            str_contains($clave, 'ubico') => 'no_ubico_direccion',
            str_contains($clave, 'predio') => 'predio_cerrado',
            str_contains($clave, 'impide') => 'usuario_impide',
            str_contains($clave, 'riesgo') => 'riesgo_sitio',
            str_contains($clave, 'terraza') => 'redes_terraza',
            str_contains($clave, 'taponado') => 'usuario_taponado',
            default => $clave,
        };
    }
}
