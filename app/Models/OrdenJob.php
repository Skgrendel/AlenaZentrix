<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrdenJob extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'orden_jobs';

    protected $fillable = [
        'personals_id',
        'numero_orden',
        'contrato',
        'cliente',
        'direccion',
        'barrio',
        'medidor',
        'tipo_medidor',
        'ciclo',
        'latitud',
        'longitud',
        'titulo',
        'descripcion',
        'prioridad',
        'estado',
        'estado_servicio',
        'fecha_asignacion',
        'fecha_limite',
        'fecha_completado',
    ];

    protected $casts = [
        'estado_servicio' => 'boolean',
        'fecha_asignacion' => 'datetime',
        'fecha_limite' => 'datetime',
        'fecha_completado' => 'datetime',
    ];

    public function personal()
    {
        return $this->belongsTo(personals::class, 'personals_id');
    }

    public function reportes()
    {
        return $this->hasMany(reportes::class, 'orden_job_id');
    }
}
