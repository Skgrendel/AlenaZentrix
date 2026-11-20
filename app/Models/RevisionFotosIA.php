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
        'survey_registro_id', 'modelo', 'detalle_imagen', 'instrucciones_hash', 'veredicto',
        'fotos', 'tokens_entrada', 'tokens_salida', 'costo_usd', 'user_id',
    ];

    protected function casts(): array
    {
        return ['veredicto' => 'array', 'costo_usd' => 'float'];
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
