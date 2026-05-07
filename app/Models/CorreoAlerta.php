<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CorreoAlerta extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'correos_alerta';

    protected $fillable = ['nombre', 'email', 'activo'];

    protected $casts = ['activo' => 'boolean'];
}
