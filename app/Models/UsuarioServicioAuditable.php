<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsuarioServicioAuditable extends Model
{
    protected $table = 'usuario_servicios_auditables';

    protected $fillable = ['user_id', 'tipo_servicio'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
