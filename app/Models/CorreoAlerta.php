<?php

namespace App\Models;

use App\Models\Scopes\CompanyScope;
use App\Services\TenantService;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Destinatario de correo que recibe notificaciones automáticas cuando se detectan incidencias graves
 * (fuga de gas, exceso de capacidad) en una orden de tipo Fraudes.
 */
class CorreoAlerta extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'correos_alerta';

    protected $fillable = ['nombre', 'email', 'activo', 'company_id'];

    protected $casts = ['activo' => 'boolean'];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function (self $model): void {
            if (empty($model->company_id)) {
                $model->company_id = app(TenantService::class)->id();
            }
        });
    }
}
