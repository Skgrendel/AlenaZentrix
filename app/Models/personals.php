<?php

namespace App\Models;

use App\Enums\TipoDocumento;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class personals extends Model
{
    use HasFactory, HasUuids;

    static $rules = [
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
        'telefono',
        'apellidos',
        'estado',
    ];

    protected $casts = [
        'tipo_documento' => TipoDocumento::class,
    ];

    public function usuario()
    {
        return $this->hasOne(User::class, 'personals_id');
    }

    public function ordenJobs()
    {
        return $this->hasMany(OrdenJob::class, 'personals_id');
    }
}
