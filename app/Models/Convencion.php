<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Catálogo de convenciones o códigos de procedimiento utilizados en los reportes de Investigación.
 */
class Convencion extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'convenciones';

    protected $fillable = ['nombre', 'activo'];

    protected $casts = ['activo' => 'boolean'];
}
