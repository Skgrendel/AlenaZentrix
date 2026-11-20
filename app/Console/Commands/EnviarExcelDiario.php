<?php

namespace App\Console\Commands;

use App\Models\EnvioExcel;
use App\Services\survey123\EnvioDiarioDeExcel;
use Illuminate\Console\Command;

class EnviarExcelDiario extends Command
{
    protected $signature = 'survey123:enviar-excel-diario';

    protected $description = 'Envía por correo el Excel de la auditoría de suspensiones a las empresas a las que ya les toca (hora, días y destinatarios de Configuración).';

    public function handle(EnvioDiarioDeExcel $envios): int
    {
        $enviados = 0;

        foreach ($envios->pendientes() as $ajuste) {
            $envio = $envios->enviar($ajuste, EnvioExcel::AUTOMATICO);

            if ($envio->estado === EnvioExcel::ENVIADO) {
                $enviados++;
                $this->info('Excel enviado a '.implode(', ', $envio->destinatarios)." ({$envio->registros} registros).");

                continue;
            }

            $this->error("No se pudo enviar el Excel de la empresa {$ajuste->company_id}: {$envio->error}");
        }

        if ($enviados === 0) {
            $this->line('Ningún envío pendiente en este momento.');
        }

        return self::SUCCESS;
    }
}
