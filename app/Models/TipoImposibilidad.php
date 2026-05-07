<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Catálogo de causas que impidieron completar la inspección en campo (acceso negado, predio cerrado, etc.).
 */
class TipoImposibilidad extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'tipos_imposibilidad';

    protected $fillable = ['nombre', 'nomenclatura', 'activo'];

    protected $casts = ['activo' => 'boolean'];
}
