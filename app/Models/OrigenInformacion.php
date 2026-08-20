<?php

namespace App\Models;

use App\Models\Scopes\CompanyScope;
use App\Services\TenantService;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Origen de la información que dio lugar a una orden de trabajo. Catálogo multi-tenant por empresa.
 */
class OrigenInformacion extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'origenes_informacion';

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

    public function ordenJobs(): HasMany
    {
        return $this->hasMany(OrdenJob::class, 'origen_informacion_id');
    }
}
