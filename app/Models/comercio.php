<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class comercio extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'comercios';

    protected $fillable = [
        'nombre_comercio',
        'tipo_comercio_id',
        'medidor_anomalia',
    ];

    public function tipoComercio()
    {
        return $this->belongsTo(TipoComercio::class, 'tipo_comercio_id');
    }

    public function reportes()
    {
        return $this->hasMany(reportes::class, 'comercios_id');
    }
}
