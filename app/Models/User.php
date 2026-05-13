<?php

namespace App\Models;

use App\Models\Scopes\CompanyScope;
use App\Services\TenantService;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * Cuenta de acceso al sistema. El nombre visible se obtiene a través de la relación `personal`.
 */
class User extends Authenticatable
{
    use HasFactory;
    use HasRoles;
    use HasUuids;
    use Notifiable;

    protected $fillable = [
        'email',
        'personals_id',
        'company_id',
        'is_superadmin',
        'password',
        'estado',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'estado' => 'boolean',
        'is_superadmin' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function (self $model): void {
            if (empty($model->company_id) && ! $model->is_superadmin) {
                $model->company_id = app(TenantService::class)->id();
            }
        });
    }

    public function personal(): BelongsTo
    {
        return $this->belongsTo(personals::class, 'personals_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /**
     * Devuelve el nombre completo del usuario. Si no tiene personal asociado,
     * retorna el email como identificador de respaldo.
     */
    public function fullName(): string
    {
        $personal = $this->personal;

        if (! $personal) {
            return $this->email;
        }

        return $personal->nombres.' '.$personal->apellidos;
    }

    /**
     * Devuelve las iniciales del nombre y apellido para avatares.
     * Retorna '?' si el usuario no tiene personal vinculado.
     */
    public function initials(): string
    {
        $personal = $this->personal;

        if (! $personal) {
            return '?';
        }

        return mb_strtoupper(
            mb_substr($personal->nombres, 0, 1).mb_substr($personal->apellidos, 0, 1)
        );
    }
}
