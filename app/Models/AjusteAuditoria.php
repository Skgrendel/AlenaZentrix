<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Qué tan grave es cada regla de la auditoría de suspensiones para una empresa. Permite ser flexible: por ejemplo,
 * una foto mal colocada no impide aprobar, solo obliga a corregir.
 */
class AjusteAuditoria extends Model
{
    use HasUuids;

    /** Cada hallazgo conserva la gravedad con que se programó la regla (error o alerta). */
    public const SEGUN_EL_CASO = 'segun_el_caso';

    /** Cuenta como error y propone «No se aprueba». */
    public const BLOQUEANTE = 'bloqueante';

    /** Cuenta como alerta y propone «Se aprueba solicitando correcciones». */
    public const CORREGIBLE = 'corregible';

    /** La regla no se aplica. */
    public const NO_APLICAR = 'no_aplicar';

    /** @var array<string, string> */
    public const NIVELES = [
        self::SEGUN_EL_CASO => 'Según el caso',
        self::BLOQUEANTE => 'Bloqueante',
        self::CORREGIBLE => 'Corregible',
        self::NO_APLICAR => 'No aplicar',
    ];

    /** @var array<string, array{nombre: string, detalle: string}> */
    public const REGLAS = [
        'tecnico' => ['nombre' => 'Técnico', 'detalle' => 'Que esté el nombre del técnico y traiga apellido.'],
        'contrato' => ['nombre' => 'Contrato', 'detalle' => 'Que esté, sea numérico y tenga una longitud normal.'],
        'orden_trabajo' => ['nombre' => 'Orden de trabajo', 'detalle' => 'Que esté, tenga los dígitos esperados y no esté repetida.'],
        'categoria' => ['nombre' => 'Categoría', 'detalle' => 'Que sea comercial, residencial o industrial.'],
        'serie_medidor' => ['nombre' => 'Serie del medidor', 'detalle' => 'Que esté, tenga una longitud normal y no se repita en otro registro.'],
        'lectura' => ['nombre' => 'Lectura del medidor', 'detalle' => 'Que esté, sea numérica y no sea negativa, cero ni excesiva.'],
        'fecha' => ['nombre' => 'Fecha de registro', 'detalle' => 'Que esté, no sea futura y sea coherente con la fecha de envío.'],
        'actividad' => ['nombre' => 'Actividad', 'detalle' => 'Que la actividad registrada sea una suspensión.'],
        'suspension' => ['nombre' => 'Datos de la suspensión', 'detalle' => 'Ejecución, método y motivo de no ejecución.'],
        'serie_sonda_caps' => ['nombre' => 'Series de la sonda y del caps', 'detalle' => 'Que estén cuando el método las exige.'],
        'justificacion' => ['nombre' => 'Justificación', 'detalle' => 'Que esté y concuerde con el método registrado.'],
        'fotos_minimas' => ['nombre' => 'Fotos mínimas', 'detalle' => 'Que estén las fotos requeridas (fachada, medidor, IVT y actividad).'],
        'fotos_repetidas' => ['nombre' => 'Fotos repetidas', 'detalle' => 'Que no haya la misma foto repetida en el registro o usada en otras visitas.'],
        'ubicacion' => ['nombre' => 'Ubicación', 'detalle' => 'Que las coordenadas existan y sean posibles en Colombia.'],
        'punto_repetido' => ['nombre' => 'Mismo punto en contratos distintos', 'detalle' => 'Que no coincida con la ubicación de otro contrato.'],
    ];

    protected $table = 'ajustes_auditoria';

    protected $fillable = ['company_id', 'pesos'];

    protected function casts(): array
    {
        return ['pesos' => 'array'];
    }

    /**
     * Nivel de cada regla para la empresa: los valores sugeridos y, encima, lo que la empresa haya guardado.
     *
     * @return array<string, string>
     */
    public static function pesosDe(?string $companyId): array
    {
        $guardado = $companyId === null ? null : self::query()->where('company_id', $companyId)->value('pesos');

        return array_merge(
            array_fill_keys(array_keys(self::REGLAS), self::SEGUN_EL_CASO),
            self::sugeridos(),
            is_array($guardado) ? array_intersect_key($guardado, self::REGLAS) : [],
        );
    }

    /** @return array<string, string> */
    public static function sugeridos(): array
    {
        return array_intersect_key((array) config('survey_auditoria.pesos_sugeridos', []), self::REGLAS);
    }
}
