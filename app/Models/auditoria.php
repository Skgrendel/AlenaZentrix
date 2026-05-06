<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class auditoria extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'auditorias';

    protected $fillable = [
        'reportes_id',
        'medidor_coincide',
        'lectura_correcta',
        'foto_correcta',
        'comercio_coincide',
        'intento_soborno',
        'observaciones',
    ];

    public function reporte()
    {
        return $this->belongsTo(reportes::class, 'reportes_id');
    }
}
