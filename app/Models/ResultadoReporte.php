<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Catálogo de resultados posibles al cerrar un reporte de Investigación o Vigilancia.
 */
class ResultadoReporte extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'resultados_reporte';

    protected $fillable = ['nombre', 'activo'];

    protected $casts = ['activo' => 'boolean'];
}
