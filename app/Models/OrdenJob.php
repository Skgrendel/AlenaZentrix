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

/**
 * Orden de trabajo que representa una tarea de campo asignada a un técnico o investigador.
 * Concentra el ciclo de vida completo: desde su creación hasta su completado y posterior auditoría.
 */
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
        'antecedente',
        'objetivo',
        'actividades',
        'numero_noticia_criminal',
        'tipo_investigacion',
        'tipo_gestion_legal',
        'documentos',
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
        'actividades' => 'array',
        'documentos' => 'array',
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

            // Vigilancia no tiene técnico asignado, arranca directo en progreso
            if ($model->tipo_servicio === TipoServicio::Vigilancia) {
                $model->estado = EstadoOrden::EnProgreso;
            } elseif (! empty($model->personals_id) && $model->estado === EstadoOrden::Pendiente) {
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
            // Solo Fraudes genera auditoría automática al completarse
            if (
                $model->wasChanged('estado') &&
                $model->estado === EstadoOrden::Completada &&
                $model->tipo_servicio === TipoServicio::Fraudes
            ) {
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

    /**
     * Genera el siguiente número de orden correlativo en formato ODT-XXXXXX.
     * Busca el máximo existente para evitar duplicados en inserciones concurrentes.
     */
    protected static function generateNumeroOrden(): string
    {
        $last = static::query()
            ->where('numero_orden', 'like', 'ODT-%')
            ->max('numero_orden');

        $sequence = $last ? ((int) substr($last, 4)) + 1 : 1;

        return 'ODT-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }

    /** Técnico o investigador asignado a ejecutar la orden. */
    public function personal(): BelongsTo
    {
        return $this->belongsTo(personals::class, 'personals_id');
    }

    /** Usuario (coordinador/admin) que creó la orden. */
    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por_id');
    }

    public function reportes(): HasMany
    {
        return $this->hasMany(reportes::class, 'orden_job_id');
    }

    public function subtipoLabel(): string
    {
        $subtipos = [
            'capturas' => 'Capturas Criminales',
            'bandas' => 'Desarticulación de Bandas',
            'querella' => 'Querella Penal',
            'seguimiento_denuncia' => 'Seguimiento a Denuncia',
            'diligencia_judicial' => 'Diligencia Judicial',
        ];

        return match ($this->tipo_servicio) {
            TipoServicio::GestionLegal => $subtipos[$this->tipo_gestion_legal ?? ''] ?? ($this->tipo_gestion_legal ?? '—'),
            TipoServicio::Investigacion => $this->tipo_investigacion ?? '—',
            default => '—',
        };
    }
}
