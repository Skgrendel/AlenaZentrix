<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Cuándo, a quién y con qué mensaje se envía por correo el Excel diario de la auditoría de suspensiones. Uno por empresa.
 */
class AjusteEnvioExcel extends Model
{
    use HasUuids;

    protected $table = 'ajustes_envio_excel';

    /** Lunes a viernes, como los números ISO de los días (1 = lunes ... 7 = domingo). */
    public const DIAS_POR_DEFECTO = [1, 2, 3, 4, 5];

    public const ASUNTO_POR_DEFECTO = 'Auditoría de suspensiones — {fecha}';

    public const MENSAJE_POR_DEFECTO = "Buen día.\n\nAdjunto el Excel con los registros de suspensiones del día para su revisión.";

    protected $fillable = ['company_id', 'activo', 'activo_desde', 'hora', 'dias', 'destinatarios', 'asunto', 'mensaje'];

    protected function casts(): array
    {
        return ['activo' => 'boolean', 'activo_desde' => 'datetime', 'dias' => 'array'];
    }

    /** @return BelongsTo<Company, $this> */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /** Ajustes de la empresa; si no se han guardado, los valores iniciales (sin crearlos y con el envío apagado). */
    public static function de(?string $companyId): self
    {
        $guardado = $companyId === null ? null : self::query()->where('company_id', $companyId)->first();

        return $guardado ?? new self([
            'company_id' => $companyId,
            'activo' => false,
            'hora' => '18:00',
            'dias' => self::DIAS_POR_DEFECTO,
            'destinatarios' => '',
            'asunto' => self::ASUNTO_POR_DEFECTO,
            'mensaje' => self::MENSAJE_POR_DEFECTO,
        ]);
    }

    /**
     * Los correos escritos separados por coma, punto y coma, espacio o salto de línea, sin repetidos.
     *
     * @return array<int, string>
     */
    public function correos(): array
    {
        return collect(preg_split('/[\s,;]+/', (string) $this->destinatarios, -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn (string $correo): string => Str::lower(trim($correo)))
            ->unique()
            ->values()
            ->all();
    }
}
