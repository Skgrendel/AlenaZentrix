<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Foto de un registro de Survey123 copiada a nuestro almacenamiento.
 */
class SurveyAdjunto extends Model
{
    use HasUuids;

    protected $table = 'survey_adjuntos';

    protected $fillable = [
        'survey_registro_id',
        'attachment_id',
        'global_id',
        'campo',
        'nombre',
        'content_type',
        'tamano',
        'ruta',
        'hash',
    ];

    /** @return BelongsTo<SurveyRegistro, $this> */
    public function registro(): BelongsTo
    {
        return $this->belongsTo(SurveyRegistro::class, 'survey_registro_id');
    }
}
