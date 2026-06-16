<?php

namespace Database\Seeders;

use App\Models\OrdenJob;
use App\Models\Proceso;
use Illuminate\Database\Seeder;

class AsignarProcesosOrdenesSeeder extends Seeder
{
    public function run(): void
    {
        $ctv = Proceso::where('nombre', 'Construcciones y Trabajos Varios')->firstOrFail();
        $sp  = Proceso::where('nombre', 'Servicios Periciales')->firstOrFail();

        $n1 = OrdenJob::where('tipo_servicio', 'auditoria_campo')->whereNull('proceso_id')->update(['proceso_id' => $ctv->id]);
        $n2 = OrdenJob::whereIn('tipo_servicio', ['fraudes', 'investigacion'])->whereNull('proceso_id')->update(['proceso_id' => $sp->id]);

        $this->command->info("auditoria_campo -> Construcciones y Trabajos Varios: $n1 registros");
        $this->command->info("fraudes/investigacion -> Servicios Periciales: $n2 registros");
    }
}
