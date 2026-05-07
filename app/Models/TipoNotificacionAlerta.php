<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TipoNotificacionAlerta extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'tipos_notificacion_alerta';

    protected $fillable = ['nombre', 'activo'];

    protected $casts = ['activo' => 'boolean'];
}
