<?php

namespace App\Models;

use App\Services\survey123\RevisorFotosIA;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Modelo, detalle de imagen e instrucciones con que se revisan las fotos de las suspensiones, por empresa.
 */
class AjusteRevisionIA extends Model
{
    use HasUuids;

    protected $table = 'ajustes_revision_ia';

    protected $fillable = ['company_id', 'modelo', 'detalle_imagen', 'instrucciones'];

    /**
     * Ajustes vigentes de la empresa; si no se han guardado, los valores recomendados (sin crearlos).
     */
    public static function de(?string $companyId): self
    {
        $guardado = $companyId === null ? null : self::query()->where('company_id', $companyId)->first();

        return $guardado ?? new self([
            'company_id' => $companyId,
            'modelo' => (string) config('services.openai.modelo'),
            'detalle_imagen' => (string) config('services.openai.detalle_imagen', 'low'),
            'instrucciones' => RevisorFotosIA::INSTRUCCIONES_RECOMENDADAS,
        ]);
    }
}
