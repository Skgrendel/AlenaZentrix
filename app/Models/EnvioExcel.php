<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Constancia de cada envío (o intento de envío) del Excel diario por correo.
 */
class EnvioExcel extends Model
{
    use HasUuids;

    public const ENVIADO = 'enviado';

    public const FALLIDO = 'fallido';

    public const AUTOMATICO = 'automatico';

    public const PRUEBA = 'prueba';

    protected $table = 'envios_excel';

    protected $fillable = ['company_id', 'origen', 'estado', 'destinatarios', 'desde', 'hasta', 'registros', 'error'];

    protected function casts(): array
    {
        return ['destinatarios' => 'array', 'desde' => 'datetime', 'hasta' => 'datetime'];
    }
}
