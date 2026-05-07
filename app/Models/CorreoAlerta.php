<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Destinatario de correo que recibe notificaciones automáticas cuando se detectan incidencias graves
 * (fuga de gas, exceso de capacidad) en una orden de tipo Fraudes.
 */
class CorreoAlerta extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'correos_alerta';

    protected $fillable = ['nombre', 'email', 'activo'];

    protected $casts = ['activo' => 'boolean'];
}
