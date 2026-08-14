<?php

namespace App\Models;

use App\Enums\EstadoAuditoria;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de auditoría generado automáticamente cuando una orden de tipo Fraudes se completa.
 * El auditor confirma o rechaza el hallazgo del técnico adjuntando evidencias.
 */
class auditoria extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'auditorias';

    protected $fillable = [
        'reportes_id',
        'auditado_por_id',
        'estado',
        'evidencias',
        'observaciones',
        'tipo_anomalia_id',
        'metros_cubicos_recuperados',
        'capacidad_instalada',
        'fecha_auditoria',
    ];

    protected $casts = [
        'estado' => EstadoAuditoria::class,
        'evidencias' => 'array',
        'fecha_auditoria' => 'datetime',
    ];

    public function reporte(): BelongsTo
    {
        return $this->belongsTo(reportes::class, 'reportes_id');
    }

    public function auditadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auditado_por_id');
    }

    public function tipoAnomalia(): BelongsTo
    {
        return $this->belongsTo(TipoAnomalia::class, 'tipo_anomalia_id');
    }
}
