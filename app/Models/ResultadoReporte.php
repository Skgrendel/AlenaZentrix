<?php

namespace App\Models;

use App\Models\Scopes\CompanyScope;
use App\Services\TenantService;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Catálogo de resultados posibles al cerrar un reporte de Investigación o Vigilancia.
 */
class ResultadoReporte extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'resultados_reporte';

    protected $fillable = ['nombre', 'activo', 'company_id'];

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
