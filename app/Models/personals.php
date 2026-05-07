<?php

namespace App\Models;

use App\Enums\TipoDocumento;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

    /** Órdenes de trabajo asignadas a este personal como técnico/investigador. */
    public function ordenJobs()
    {
        return $this->hasMany(OrdenJob::class, 'personals_id');
    }
}
