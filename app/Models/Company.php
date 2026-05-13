<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = ['nombre', 'logo', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'company_id');
    }

    public function ordenJobs(): HasMany
    {
        return $this->hasMany(OrdenJob::class, 'company_id');
    }
}
