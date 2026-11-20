<?php

namespace App\Models;

use App\Enums\EstadoSurveyRegistro;
use App\Models\Scopes\CompanyScope;
use App\Services\TenantService;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Suspensión reportada por un técnico en Survey123, a la espera de ser auditada en campo.
 */
class SurveyRegistro extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'survey_registros';

    /** @var array<string, string> */
    public const METODOS = [
        'sonda_ips' => 'Sonda (Dispositivo IPS)',
        'sonda_cts' => 'Sonda (Dispositivo CTS)',
        'taponamiento_acometida' => 'Taponamiento de acometida',
        'taponamiento_multifamiliar' => 'Taponamiento Multifamiliar',
    ];

    /** @var array<string, string> */
    public const MOTIVOS = [
        'no_ubico_direccion' => 'No se ubicó la dirección',
        'predio_cerrado' => 'Predio cerrado o desocupado',
        'usuario_impide' => 'El usuario impide el procedimiento',
        'riesgo_sitio' => 'Riesgo en sitio (perro, altura, etc.)',
        'redes_terraza' => 'Redes por terraza',
        'usuario_taponado' => 'Se encontró el usuario taponado en campo',
    ];

    protected $fillable = [
        'company_id',
        'object_id',
        'global_id',
        'fecha_registro',
        'tecnico',
        'contrato',
        'orden_trabajo',
        'categoria',
        'serie_medidor',
        'lectura_medidor',
        'actividad',
        'suspension_ejecutada',
        'metodo_suspension',
        'serie_sonda',
        'serie_caps',
        'motivo_no_ejecucion',
        'justificacion',
        'latitud',
        'longitud',
        'creado_en_origen',
        'editado_en_origen',
        'payload',
        'estado',
    ];

    protected $casts = [
        'fecha_registro' => 'datetime',
        'creado_en_origen' => 'datetime',
        'editado_en_origen' => 'datetime',
        'suspension_ejecutada' => 'boolean',
        'payload' => 'array',
        'hallazgos' => 'array',
        'auditado_en' => 'datetime',
        'estado' => EstadoSurveyRegistro::class,
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function (self $model): void {
            if (empty($model->company_id)) {
                $model->company_id = app(TenantService::class)->id();
            }
        });
    }

    /** @return HasMany<SurveyAdjunto, $this> */
    public function adjuntos(): HasMany
    {
        return $this->hasMany(SurveyAdjunto::class, 'survey_registro_id');
    }

    /** @return HasMany<RevisionFotosIA, $this> */
    public function revisionesIA(): HasMany
    {
        return $this->hasMany(RevisionFotosIA::class, 'survey_registro_id');
    }

    /** @return HasMany<OrdenJob, $this> */
    public function ordenes(): HasMany
    {
        return $this->hasMany(OrdenJob::class, 'survey_registro_id');
    }
}
