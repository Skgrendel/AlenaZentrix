<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ubicacion extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'ubicacions';

    protected $fillable = [
        'direccion',
        'latitud',
        'longitud',
    ];

    public function reporte()
    {
        return $this->belongsTo(reportes::class, 'ubicacions_id');
    }
}
