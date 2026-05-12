<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Reporte de campo registrado por el técnico al ejecutar una orden de trabajo.
 * `personals_id` identifica a quien creó el reporte, no necesariamente al asignado de la orden.
 */
class reportes extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'ubicacions_id',
        'comercios_id',
        'orden_job_id',
        'personals_id',
        'numero_orden',
        'numero_medidor',
        'coincide_medidor',
        'lectura',
        'anomalia',
        'tipo_imposibilidad_id',
        'tipo_regulador_id',
        'marca_medidor_id',
        'marca_regulador_id',
        'tipo_notificacion_alerta_id',
        'actividades',
        'hay_fuga_gas',
        'excede_capacidad',
        'imagenes',
        'video',
        'observaciones',
        'comentarios',
        'datos_servicio',
        'revisado',
        'confirmado',
        'estado',
    ];

    protected $casts = [
        'anomalia' => 'array',
        'imagenes' => 'array',
        'actividades' => 'array',
        'datos_servicio' => 'array',
        'coincide_medidor' => 'boolean',
        'hay_fuga_gas' => 'boolean',
        'excede_capacidad' => 'boolean',
        'revisado' => 'boolean',
        'confirmado' => 'boolean',
    ];

    public function ordenJob()
    {
        return $this->belongsTo(OrdenJob::class, 'orden_job_id');
    }

    public function personal()
    {
        return $this->belongsTo(personals::class, 'personals_id');
    }

    public function ubicacion()
    {
        return $this->belongsTo(ubicacion::class, 'ubicacions_id');
    }

    public function comercio()
    {
        return $this->belongsTo(comercio::class, 'comercios_id');
    }

    public function tipoImposibilidad()
    {
        return $this->belongsTo(TipoImposibilidad::class, 'tipo_imposibilidad_id');
    }

    public function tipoRegulador()
    {
        return $this->belongsTo(TipoRegulador::class, 'tipo_regulador_id');
    }

    public function marcaMedidor()
    {
        return $this->belongsTo(MarcaMedidor::class, 'marca_medidor_id');
    }

    public function marcaRegulador()
    {
        return $this->belongsTo(MarcaRegulador::class, 'marca_regulador_id');
    }

    public function tipoNotificacionAlerta()
    {
        return $this->belongsTo(TipoNotificacionAlerta::class, 'tipo_notificacion_alerta_id');
    }

    public function auditoria()
    {
        return $this->hasOne(auditoria::class, 'reportes_id');
    }
}
