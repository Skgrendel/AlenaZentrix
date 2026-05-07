<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory;
    use HasRoles;
    use HasUuids;
    use Notifiable;

    protected $fillable = [
        'email',
        'personals_id',
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
    ];

    public function personal()
    {
        return $this->belongsTo(personals::class, 'personals_id');
    }

    public function fullName(): string
    {
        $personal = $this->personal;

        if (! $personal) {
            return $this->email;
        }

        return $personal->nombres.' '.$personal->apellidos;
    }

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
