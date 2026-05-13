<?php

namespace App\Models;

use App\Models\Scopes\CompanyScope;
use App\Services\TenantService;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Catálogo de causas que impidieron completar la inspección en campo (acceso negado, predio cerrado, etc.).
 */
class TipoImposibilidad extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'tipos_imposibilidad';

    protected $fillable = ['nombre', 'nomenclatura', 'activo', 'company_id'];

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
