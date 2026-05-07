<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Catálogo de tipos de notificación de alerta registrables en el reporte de una inspección de Fraudes.
 */
class TipoNotificacionAlerta extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'tipos_notificacion_alerta';

    protected $fillable = ['nombre', 'activo'];

    protected $casts = ['activo' => 'boolean'];
}
