<?php

namespace App\Models;

use App\Enums\TipoDocumento;
use App\Models\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Datos personales y de identificación de técnicos, investigadores y cualquier miembro del equipo.
 */
class personals extends Model
{
    use HasFactory, HasUuids;

    public static $rules = [
        'tipo_documento' => 'required',
        'numero_documento' => 'required',
        'nombres' => 'required|string',
        'apellidos' => 'required|string',
        'telefono' => 'required',
    ];

    protected $fillable = [
        'tipo_documento',
        'numero_documento',
        'nombres',
        'apellidos',
        'telefono',
        'cargo',
        'company_id',
    ];

    protected $casts = [
        'tipo_documento' => TipoDocumento::class,
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function usuario(): HasOne
    {
        return $this->hasOne(User::class, 'personals_id');
    }

    /** Órdenes de trabajo asignadas a este personal como técnico/investigador. */
    public function ordenJobs(): HasMany
    {
        return $this->hasMany(OrdenJob::class, 'personals_id');
    }
}
