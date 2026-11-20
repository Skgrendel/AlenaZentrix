<?php

namespace App\Services\survey123;

use App\Exports\SurveyAuditoria\SurveyAuditoriaExport;
use App\Mail\ExcelDiarioDeAuditoria;
use App\Models\AjusteEnvioExcel;
use App\Models\Company;
use App\Models\EnvioExcel;
use App\Models\SurveyRegistro;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Excel as TipoExcel;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/**
 * Envía por correo el Excel de la auditoría de suspensiones, a la hora, los días y los destinatarios que cada empresa configura.
 * Cada correo trae los registros que llegaron al sistema desde el último envío, así no queda nada por fuera aunque un
 * registro tarde en sincronizarse. Todo en hora de Colombia (la zona de la aplicación).
 */
class EnvioDiarioDeExcel
{
    /** Si un envío falla se reintenta cada tanto, y se deja de insistir después de estos intentos en el mismo día. */
    public const MINUTOS_ENTRE_INTENTOS = 15;

    public const INTENTOS_POR_DIA = 4;

    /**
     * Empresas a las que ya les toca el envío automático: está encendido, hoy es uno de sus días, ya pasó su hora y todavía
     * no se envió hoy. Si el servidor estuvo apagado a esa hora, se envía apenas vuelva (el mismo día), una sola vez.
     *
     * @return Collection<int, AjusteEnvioExcel>
     */
    public function pendientes(?CarbonInterface $ahora = null): Collection
    {
        $ahora ??= now();
        $inicioDelDia = $ahora->copy()->startOfDay();

        return AjusteEnvioExcel::query()
            ->where('activo', true)
            ->where('hora', '<=', $ahora->format('H:i'))
            ->get()
            ->filter(fn (AjusteEnvioExcel $a): bool => in_array($ahora->dayOfWeekIso, array_map('intval', $a->dias ?? []), true))
            ->reject(fn (AjusteEnvioExcel $a): bool => $this->yaSeEnvioHoy($a, $inicioDelDia) || $this->esperaParaReintentar($a, $inicioDelDia, $ahora))
            ->values();
    }

    /**
     * Arma y envía el correo. $origen es «automatico» o «prueba»; en una prueba se pasan los destinatarios (por ejemplo, solo
     * quien la pide). Siempre deja constancia, también cuando falla, y nunca lanza una excepción.
     *
     * @param  array<int, string>|null  $destinatarios
     */
    public function enviar(AjusteEnvioExcel $ajuste, string $origen = EnvioExcel::AUTOMATICO, ?array $destinatarios = null, ?CarbonInterface $ahora = null): EnvioExcel
    {
        $ahora ??= now();
        $destinatarios ??= $ajuste->correos();
        $desde = $this->desdeDelEnvio($ajuste, $ahora);
        $registros = SurveyRegistro::query()
            ->withoutGlobalScopes()
            ->where('company_id', $ajuste->company_id)
            ->where('created_at', '>', $desde)
            ->where('created_at', '<=', $ahora);

        $total = 0;

        try {
            $total = (clone $registros)->count();
            $conErrores = (clone $registros)->where('total_errores', '>', 0)->count();
            $conAlertas = (clone $registros)->where('total_alertas', '>', 0)->count();
            $empresa = Company::query()->withoutGlobalScopes()->find($ajuste->company_id)?->nombre ?? 'Alena Zentrix';
            $fecha = $ahora->format('d/m/Y');
            $formato = 'd/m/Y h:i a';

            $contenido = null;

            if ($total > 0) {
                SurveyAuditoriaExport::ampliarRecursos();
                $contenido = Excel::raw(new SurveyAuditoriaExport($registros, [
                    'empresa' => $empresa,
                    'generado_por' => $origen === EnvioExcel::PRUEBA ? 'Envío de prueba' : 'Envío automático',
                    'filtros' => ["Registros recibidos entre {$desde->format($formato)} y {$ahora->format($formato)}"],
                ]), TipoExcel::XLSX);
            }

            Mail::to($destinatarios)->send(new ExcelDiarioDeAuditoria(
                asunto: str_replace(['{fecha}', '{registros}'], [$fecha, (string) $total], $ajuste->asunto),
                mensaje: $ajuste->mensaje,
                empresa: $empresa,
                desde: $desde->format($formato),
                hasta: $ahora->format($formato),
                registros: $total,
                conErrores: $conErrores,
                conAlertas: $conAlertas,
                contenidoExcel: $contenido,
                nombreDelArchivo: 'auditoria-suspensiones-'.$ahora->format('Ymd-Hi').'.xlsx',
            ));

            return $this->constancia($ajuste, $origen, EnvioExcel::ENVIADO, $destinatarios, $total, $desde, $ahora);
        } catch (Throwable $e) {
            Log::error('No se pudo enviar el Excel diario por correo.', ['empresa' => $ajuste->company_id, 'error' => $e->getMessage()]);

            return $this->constancia($ajuste, $origen, EnvioExcel::FALLIDO, $destinatarios, $total, $desde, $ahora, mb_substr($e->getMessage(), 0, 500));
        }
    }

    /**
     * Desde cuándo cubre el próximo correo: el momento hasta el que llegó el último envío exitoso. Si todavía no hubo
     * ninguno, desde que se encendió el envío (así no se manda de golpe todo lo que ya estaba cargado); y si nunca se
     * encendió (una prueba), desde las 00:00 de hoy. Un envío fallido no mueve el corte: lo que no salió se manda en el siguiente.
     */
    public function desdeDelEnvio(AjusteEnvioExcel $ajuste, CarbonInterface $ahora): CarbonInterface
    {
        $ultimo = EnvioExcel::query()
            ->where('company_id', $ajuste->company_id)
            ->where('origen', EnvioExcel::AUTOMATICO)
            ->where('estado', EnvioExcel::ENVIADO)
            ->whereNotNull('hasta')
            ->max('hasta');

        $candidatos = array_values(array_filter([$ultimo === null ? null : Carbon::parse($ultimo), $ajuste->activo_desde]));

        if ($candidatos === []) {
            return $ahora->copy()->startOfDay();
        }

        usort($candidatos, fn ($a, $b): int => $b->getTimestamp() <=> $a->getTimestamp());

        return $candidatos[0];
    }

    /** @param  array<int, string>  $destinatarios */
    private function constancia(AjusteEnvioExcel $ajuste, string $origen, string $estado, array $destinatarios, int $registros, CarbonInterface $desde, CarbonInterface $hasta, ?string $error = null): EnvioExcel
    {
        return EnvioExcel::query()->create([
            'company_id' => $ajuste->company_id,
            'origen' => $origen,
            'estado' => $estado,
            'destinatarios' => $destinatarios,
            'desde' => $desde,
            'hasta' => $hasta,
            'registros' => $registros,
            'error' => $error,
        ]);
    }

    private function yaSeEnvioHoy(AjusteEnvioExcel $ajuste, CarbonInterface $inicioDelDia): bool
    {
        return EnvioExcel::query()
            ->where('company_id', $ajuste->company_id)
            ->where('origen', EnvioExcel::AUTOMATICO)
            ->where('estado', EnvioExcel::ENVIADO)
            ->where('created_at', '>=', $inicioDelDia)
            ->exists();
    }

    /** Después de un fallo se espera un rato para reintentar, y se rinde al llegar al máximo de intentos del día. */
    private function esperaParaReintentar(AjusteEnvioExcel $ajuste, CarbonInterface $inicioDelDia, CarbonInterface $ahora): bool
    {
        $fallos = EnvioExcel::query()
            ->where('company_id', $ajuste->company_id)
            ->where('origen', EnvioExcel::AUTOMATICO)
            ->where('estado', EnvioExcel::FALLIDO)
            ->where('created_at', '>=', $inicioDelDia);

        if ((clone $fallos)->count() >= self::INTENTOS_POR_DIA) {
            return true;
        }

        $ultimo = (clone $fallos)->max('created_at');

        return $ultimo !== null && Carbon::parse($ultimo)->addMinutes(self::MINUTOS_ENTRE_INTENTOS)->gt($ahora);
    }
}
