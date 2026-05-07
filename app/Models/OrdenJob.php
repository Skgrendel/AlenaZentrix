<?php

namespace App\Models;

use App\Enums\EstadoAuditoria;
use App\Enums\EstadoOrden;
use App\Enums\PrioridadOrden;
use App\Enums\TipoServicio;
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
        'creado_por_id',
        'tipo_servicio',
        'numero_orden',
        'contrato',
        'cliente',
        'direccion',
        'barrio',
        'medidor',
        'tipo_medidor',
        'ciclo',
        'numero_noticia_criminal',
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
        'tipo_servicio' => TipoServicio::class,
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

            if (empty($model->creado_por_id) && auth()->check()) {
                $model->creado_por_id = auth()->id();
            }

            if (empty($model->fecha_asignacion)) {
                $model->fecha_asignacion = now();
            }

            if (! empty($model->personals_id) && $model->estado === EstadoOrden::Pendiente) {
                $model->estado = EstadoOrden::Asignada;
            }
        });

        static::updating(function (self $model): void {
            if (
                $model->isDirty('personals_id') &&
                ! empty($model->personals_id) &&
                $model->estado === EstadoOrden::Pendiente
            ) {
                $model->estado = EstadoOrden::Asignada;
            }

            if (
                $model->isDirty('estado') &&
                $model->estado === EstadoOrden::Completada &&
                $model->getOriginal('fecha_completado') === null
            ) {
                $model->fecha_completado = now();
            }
        });

        static::updated(function (self $model): void {
            if ($model->wasChanged('estado') && $model->estado === EstadoOrden::Completada) {
                $reporte = $model->reportes()->latest()->first();

                if ($reporte) {
                    auditoria::create([
                        'reportes_id' => $reporte->id,
                        'estado' => EstadoAuditoria::PendienteAuditoria,
                    ]);
                }
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

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por_id');
    }

    public function reportes(): HasMany
    {
        return $this->hasMany(reportes::class, 'orden_job_id');
    }
}
