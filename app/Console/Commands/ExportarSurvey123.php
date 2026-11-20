<?php

namespace App\Console\Commands;

use App\Enums\EstadoSurveyRegistro;
use App\Exports\SurveyAuditoria\FiltrosSurveyExportacion;
use App\Exports\SurveyAuditoria\SurveyAuditoriaExport;
use App\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class ExportarSurvey123 extends Command
{
    protected $signature = 'survey123:exportar
        {--desde= : Solo registros con fecha de registro desde este día (Y-m-d)}
        {--hasta= : Solo registros con fecha de registro hasta este día (Y-m-d)}
        {--estado= : Solo registros pendientes o asignados}
        {--con-errores : Solo registros con errores}
        {--con-alertas : Solo registros con alertas}
        {--company= : ID de la empresa dueña de los registros (si hay una sola, se usa esa)}
        {--salida= : Ruta del archivo .xlsx (por defecto storage/app/exportaciones/auditoria-suspensiones-AAAAmmdd-HHmmss.xlsx)}';

    protected $description = 'Exporta a Excel la auditoría de suspensiones de Survey123 (registros, visitas de auditoría y resumen).';

    public function handle(): int
    {
        try {
            $filtros = $this->filtros();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($filtros === null) {
            return self::FAILURE;
        }

        $ruta = $this->option('salida') ?: storage_path('app/exportaciones/'.SurveyAuditoriaExport::nombreDeArchivo());
        $total = $filtros->query()->count();

        SurveyAuditoriaExport::ampliarRecursos();
        File::ensureDirectoryExists(dirname($ruta));

        Config::set('filesystems.disks.survey_exportacion', ['driver' => 'local', 'root' => dirname($ruta), 'throw' => true]);

        try {
            Excel::store(SurveyAuditoriaExport::conFiltros($filtros), basename($ruta), 'survey_exportacion');
        } catch (Throwable $e) {
            $this->error('No se pudo generar el archivo: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("{$total} registros exportados a {$ruta}");

        return self::SUCCESS;
    }

    private function filtros(): ?FiltrosSurveyExportacion
    {
        $companyId = $this->resolverEmpresa();

        if ($companyId === null) {
            return null;
        }

        $estado = null;

        if ($this->option('estado')) {
            $estado = EstadoSurveyRegistro::tryFrom((string) $this->option('estado'));

            if ($estado === null) {
                $this->error('El estado debe ser "pendiente" o "asignado".');

                return null;
            }
        }

        return new FiltrosSurveyExportacion(
            companyId: $companyId,
            estado: $estado,
            soloConErrores: (bool) $this->option('con-errores'),
            soloConAlertas: (bool) $this->option('con-alertas'),
            desde: $this->option('desde') ? Carbon::parse($this->option('desde')) : null,
            hasta: $this->option('hasta') ? Carbon::parse($this->option('hasta')) : null,
        );
    }

    private function resolverEmpresa(): ?string
    {
        if ($this->option('company')) {
            return (string) $this->option('company');
        }

        $empresas = Company::query()->limit(2)->pluck('id');

        if ($empresas->count() !== 1) {
            $this->error('Hay varias empresas o ninguna: indica cuál con --company=ID.');

            return null;
        }

        return $empresas->first();
    }
}
