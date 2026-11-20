<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Veredicto de la IA sobre las fotos de un registro. Es una sugerencia: no cambia la auditoría.
 */
class RevisionFotosIA extends Model
{
    use HasUuids;

    protected $table = 'revisiones_ia_survey';

    protected $fillable = [
        'survey_registro_id', 'modelo', 'detalle_imagen', 'instrucciones_hash', 'veredicto', 'con_observaciones',
        'fotos', 'tokens_entrada', 'tokens_salida', 'costo_usd', 'user_id',
    ];

    protected function casts(): array
    {
        return ['veredicto' => 'array', 'costo_usd' => 'float', 'con_observaciones' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $revision): void {
            $revision->con_observaciones = self::hayObservaciones((array) $revision->veredicto);
        });
    }

    /**
     * Si el veredicto señala algo que una persona debería mirar: hallazgos de error o alerta, fotos repetidas,
     * fotos en un campo que no les corresponde o datos leídos que no coinciden con lo reportado.
     *
     * @param  array<string, mixed>  $veredicto
     */
    public static function hayObservaciones(array $veredicto): bool
    {
        $conHallazgos = collect($veredicto['hallazgos'] ?? [])->contains(fn (array $h): bool => in_array($h['severidad'] ?? null, ['error', 'alerta'], true));
        $fueraDeCampo = collect($veredicto['fotos'] ?? [])->contains(fn (array $f): bool => ($f['coincide_con_campo'] ?? true) === false);
        $noCoinciden = collect($veredicto['datos_leidos'] ?? [])->only(['contrato_coincide', 'serie_coincide', 'lectura_coincide'])->contains(fn (mixed $coincide): bool => $coincide === false);

        return $conHallazgos || $fueraDeCampo || $noCoinciden || ($veredicto['fotos_repetidas'] ?? false) === true;
    }

    /** @return BelongsTo<SurveyRegistro, $this> */
    public function registro(): BelongsTo
    {
        return $this->belongsTo(SurveyRegistro::class, 'survey_registro_id');
    }

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
