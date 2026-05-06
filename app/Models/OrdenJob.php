<?php

namespace App\Models;

use App\Enums\EstadoOrden;
use App\Enums\PrioridadOrden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'fotos',
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
        'prioridad' => PrioridadOrden::class,
        'estado' => EstadoOrden::class,
        'fotos' => 'array',
        'estado_servicio' => 'boolean',
        'fecha_asignacion' => 'datetime',
        'fecha_limite' => 'datetime',
        'fecha_completado' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->numero_orden)) {
                $model->numero_orden = self::generateNumeroOrden();
            }
        });

        static::updating(function (self $model): void {
            if (
                $model->isDirty('estado') &&
                $model->estado === EstadoOrden::Completada &&
                $model->getOriginal('fecha_completado') === null
            ) {
                $model->fecha_completado = now();
            }
        });
    }

    protected static function generateNumeroOrden(): string
    {
        $last = static::query()
            ->where('numero_orden', 'like', 'ODT-%')
            ->max('numero_orden');

        $sequence = $last ? ((int) substr($last, 4)) + 1 : 1;

        return 'ODT-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }

    public function personal(): BelongsTo
    {
        return $this->belongsTo(personals::class, 'personals_id');
    }

    public function reportes(): HasMany
    {
        return $this->hasMany(reportes::class, 'orden_job_id');
    }
}
