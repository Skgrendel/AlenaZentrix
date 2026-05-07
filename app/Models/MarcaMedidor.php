<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Catálogo de marcas de medidores de gas utilizados en las inspecciones de Fraudes.
 */
class MarcaMedidor extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'marcas_medidor';

    protected $fillable = ['nombre', 'nomenclatura', 'activo'];

    protected $casts = ['activo' => 'boolean'];
}
