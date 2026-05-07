<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Catálogo de anomalías detectables durante una inspección de Fraudes (p.ej. bypass, manipulación).
 */
class TipoAnomalia extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'tipos_anomalia';

    protected $fillable = ['nombre', 'nomenclatura', 'activo'];

    protected $casts = ['activo' => 'boolean'];
}
