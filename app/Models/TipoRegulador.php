<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Catálogo de tipos de presión / reguladores de gas inspeccionados en órdenes de tipo Fraudes.
 */
class TipoRegulador extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'tipos_regulador';

    protected $fillable = ['nombre', 'nomenclatura', 'activo'];

    protected $casts = ['activo' => 'boolean'];
}
