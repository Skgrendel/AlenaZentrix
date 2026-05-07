<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Catálogo de marcas de reguladores de gas inspeccionados en órdenes de tipo Fraudes.
 */
class MarcaRegulador extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'marcas_regulador';

    protected $fillable = ['nombre', 'nomenclatura', 'activo'];

    protected $casts = ['activo' => 'boolean'];
}
