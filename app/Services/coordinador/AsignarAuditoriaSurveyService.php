<?php

namespace App\Services\coordinador;

use App\Enums\EstadoOrden;
use App\Enums\EstadoSurveyRegistro;
use App\Enums\PrioridadOrden;
use App\Enums\TipoServicio;
use App\Mail\OrdenAsignada;
use App\Models\OrdenJob;
use App\Models\OrigenInformacion;
use App\Models\personals;
use App\Models\Proceso;
use App\Models\Scopes\CompanyScope;
use App\Models\SurveyRegistro;
use App\Services\AvisoDeOrdenAsignada;
use App\Services\survey123\ConsultaGisContrato;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Convierte un registro de suspensión de Survey123 en una orden de auditoría de campo
 * (Visita de Calidad — Suspensiones), con los datos reportados como referencia.
 */
class AsignarAuditoriaSurveyService
{
    /** Horas máximas, desde la asignación, para realizar la visita de auditoría. */
    public const PLAZO_HORAS = 48;

    /** Nombre del origen de información y del proceso que se asignan a estas órdenes. */
    public const NOMBRE_EN_CATALOGOS = 'Auditoría de Suspensiones';

    public function __construct(private readonly ConsultaGisContrato $gis) {}

    /**
     * @throws DomainException si el registro ya tiene una auditoría vigente o el auditor no pertenece a la empresa.
     */
    public function asignar(SurveyRegistro $registro, string $personalsId, ?Carbon $fechaLimite = null): OrdenJob
    {
        $usuario = $this->datosDelUsuario($registro);

        $orden = DB::transaction(function () use ($registro, $personalsId, $fechaLimite, $usuario): OrdenJob {
            $registro = SurveyRegistro::query()->lockForUpdate()->findOrFail($registro->id);

            if ($this->tieneAuditoriaVigente($registro)) {
                throw new DomainException('Este registro ya tiene una auditoría de campo asignada.');
            }

            $auditor = personals::query()
                ->withoutGlobalScopes()
                ->where('company_id', $registro->company_id)
                ->find($personalsId);

            if (! $auditor) {
                throw new DomainException('El auditor seleccionado no pertenece a la empresa del registro.');
            }

            $orden = OrdenJob::create([
                'company_id' => $registro->company_id,
                'tipo_servicio' => TipoServicio::AuditoriaCampo,
                'tipo_auditoria_campo' => 'visita_calidad_suspensiones',
                'personals_id' => $auditor->id,
                'estado' => EstadoOrden::Asignada,
                'prioridad' => PrioridadOrden::Media,
                'estado_servicio' => true,
                'survey_registro_id' => $registro->id,
                'proceso_id' => $this->catalogo(Proceso::class, $registro->company_id),
                'origen_informacion_id' => $this->catalogo(OrigenInformacion::class, $registro->company_id),
                'contrato' => $registro->contrato,
                'tecnico' => $registro->tecnico,
                'cliente' => $usuario['nombre'] ?? null,
                'direccion' => $usuario['direccion'] ?? null,
                'barrio' => $usuario['barrio'] ?? null,
                'lectura_referencia' => $registro->lectura_medidor,
                'serie_medidor_referencia' => $registro->serie_medidor,
                'latitud_referencia' => $registro->latitud,
                'longitud_referencia' => $registro->longitud,
                'fecha_limite' => $fechaLimite ?? now()->addHours(self::PLAZO_HORAS),
            ]);

            $registro->update(['estado' => EstadoSurveyRegistro::Asignado]);

            return $orden;
        });

        $this->notificarAuditor($orden);

        return $orden;
    }

    /**
     * Id de la entrada "Auditoría de Suspensiones" del catálogo en la empresa: la usa si existe y la crea si no.
     * Se verifica en cada asignación para que borrar o renombrar la entrada no deje órdenes sin origen o proceso.
     *
     * @param  class-string<Proceso|OrigenInformacion>  $modelo
     */
    private function catalogo(string $modelo, string $companyId): string
    {
        $existente = $modelo::query()
            ->withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId)
            ->where('nombre', self::NOMBRE_EN_CATALOGOS)
            ->first();

        return ($existente ?? $modelo::query()->create([
            'company_id' => $companyId,
            'nombre' => self::NOMBRE_EN_CATALOGOS,
            'activo' => true,
        ]))->id;
    }

    /**
     * Nombre, dirección y barrio del usuario según el GIS. Si el GIS no responde,
     * la orden se asigna igual y esos datos quedan vacíos.
     *
     * @return array{nombre: ?string, direccion: ?string, barrio: ?string}|null
     */
    private function datosDelUsuario(SurveyRegistro $registro): ?array
    {
        try {
            return $this->gis->datosBasicos($registro->contrato);
        } catch (Throwable $e) {
            Log::warning('No se pudo consultar el GIS al asignar la auditoría.', [
                'registro' => $registro->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function tieneAuditoriaVigente(SurveyRegistro $registro): bool
    {
        return $registro->ordenes()
            ->withoutGlobalScopes()
            ->whereNotIn('estado', [EstadoOrden::Cancelada->value, EstadoOrden::Rechazada->value])
            ->exists();
    }

    private function notificarAuditor(OrdenJob $orden): void
    {
        AvisoDeOrdenAsignada::enviar($orden);

        $email = $orden->personal?->usuario?->email;

        if (! $email) {
            return;
        }

        try {
            Mail::to($email)->send(new OrdenAsignada($orden, $orden->personal));
        } catch (\Exception $e) {
            Log::error('No se pudo enviar notificación de orden asignada.', [
                'orden' => $orden->numero_orden,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
