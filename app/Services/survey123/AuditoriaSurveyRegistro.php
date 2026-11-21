<?php

namespace App\Services\survey123;

use App\Models\AjusteAuditoria;
use App\Models\SurveyAdjunto;
use App\Models\SurveyRegistro;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Reglas de auditoría automática de un registro de suspensión de Survey123.
 * Cada hallazgo es un error (bloqueante: el dato es inválido o falta) o una alerta (corregible: conviene revisarlo).
 * Cada empresa puede ajustar qué tan grave es cada regla (AjusteAuditoria), por ejemplo para que una foto mal colocada
 * obligue a corregir pero no impida aprobar.
 */
class AuditoriaSurveyRegistro
{
    /** @var array<string, string> nivel de cada regla para la empresa del registro que se está auditando */
    private array $pesos = [];

    private ?string $pesosDeLaEmpresa = null;

    public const ERROR = 'error';

    public const ALERTA = 'alerta';

    /**
     * @return array<int, array{regla: string, severidad: string, campo: string, mensaje: string}>
     */
    public function auditar(SurveyRegistro $registro): array
    {
        $registro->loadMissing('adjuntos');
        $this->cargarPesos($registro->company_id);

        $hallazgos = array_merge(
            $this->datosBasicos($registro),
            $this->medidor($registro),
            $this->fecha($registro),
            $this->suspension($registro),
            $this->fotos($registro),
            $this->ubicacion($registro),
        );

        // Las reglas apagadas en config devuelven un arreglo vacío.
        return array_values(array_filter($hallazgos));
    }

    public function auditarYGuardar(SurveyRegistro $registro): SurveyRegistro
    {
        $hallazgos = $this->auditar($registro);

        $registro->forceFill([
            'hallazgos' => $hallazgos,
            'total_errores' => count(array_filter($hallazgos, fn (array $h): bool => $h['severidad'] === self::ERROR)),
            'total_alertas' => count(array_filter($hallazgos, fn (array $h): bool => $h['severidad'] === self::ALERTA)),
            'auditado_en' => now(),
        ])->save();

        return $registro;
    }

    /** @return array<int, array<string, string>> */
    private function datosBasicos(SurveyRegistro $r): array
    {
        $h = [];

        if (blank($r->tecnico)) {
            $h[] = $this->hallazgo('tecnico', self::ERROR, 'tecnico', 'Falta el nombre del técnico.');
        } elseif (! str_contains(trim($r->tecnico), ' ')) {
            $h[] = $this->hallazgo('tecnico', self::ALERTA, 'tecnico', 'El nombre del técnico no incluye apellido.');
        }

        if (blank($r->contrato)) {
            $h[] = $this->hallazgo('contrato', self::ERROR, 'contrato', 'Falta el contrato.');
        } else {
            $largo = strlen((string) $r->contrato);

            if (! ctype_digit((string) $r->contrato)) {
                $h[] = $this->hallazgo('contrato', self::ERROR, 'contrato', 'El contrato debe ser numérico.');
            } elseif ($largo < $this->param('contrato_digitos_min') || $largo > $this->param('contrato_digitos_max')) {
                $h[] = $this->hallazgo('contrato', self::ALERTA, 'contrato', "El contrato tiene {$largo} dígitos, longitud atípica.");
            }
        }

        if (blank($r->orden_trabajo)) {
            $h[] = $this->hallazgo('orden_trabajo', self::ERROR, 'orden_trabajo', 'Falta la orden de trabajo.');
        } else {
            $esperados = $this->param('ot_digitos');

            if (strlen((string) $r->orden_trabajo) !== $esperados) {
                $h[] = $this->hallazgo('orden_trabajo', self::ALERTA, 'orden_trabajo', "La orden de trabajo no tiene {$esperados} dígitos.");
            }

            $repetida = $this->otros($r)->where('orden_trabajo', $r->orden_trabajo)->exists();

            if ($repetida) {
                $h[] = $this->hallazgo('orden_trabajo', self::ALERTA, 'orden_trabajo', 'La orden de trabajo está repetida en otro registro.');
            }
        }

        if (! in_array($r->categoria, ['comercial', 'residencial', 'industrial'], true)) {
            $h[] = $this->hallazgo('categoria', self::ERROR, 'categoria', 'La categoría no corresponde a Comercial, Residencial o Industrial.');
        }

        return $h;
    }

    /** @return array<int, array<string, string>> */
    private function medidor(SurveyRegistro $r): array
    {
        $h = [];

        if ($r->suspension_ejecutada !== true) {
            return $h;
        }

        if (blank($r->serie_medidor)) {
            $h[] = $this->hallazgo('serie_medidor', self::ERROR, 'serie_medidor', 'Falta la serie del medidor.');
        } else {
            $largo = strlen((string) $r->serie_medidor);

            if ($largo < $this->param('serie_medidor_min') || $largo > $this->param('serie_medidor_max')) {
                $h[] = $this->hallazgo('serie_medidor', self::ALERTA, 'serie_medidor', "La serie del medidor tiene {$largo} dígitos, longitud atípica.");
            }

            if ($this->otros($r)->where('serie_medidor', $r->serie_medidor)->exists()) {
                $h[] = $this->hallazgo('serie_medidor', self::ALERTA, 'serie_medidor', 'La serie del medidor está repetida en otro registro.');
            }
        }

        if (blank($r->lectura_medidor)) {
            $h[] = $this->hallazgo('lectura', self::ERROR, 'lectura_medidor', 'Falta la lectura del medidor.');
        } elseif (! is_numeric($r->lectura_medidor)) {
            $h[] = $this->hallazgo('lectura', self::ERROR, 'lectura_medidor', 'La lectura del medidor no es numérica.');
        } elseif ((float) $r->lectura_medidor < 0) {
            $h[] = $this->hallazgo('lectura', self::ERROR, 'lectura_medidor', 'La lectura del medidor es negativa.');
        } elseif ((float) $r->lectura_medidor === 0.0) {
            $h[] = $this->hallazgo('lectura', self::ALERTA, 'lectura_medidor', 'La lectura del medidor es cero.');
        } elseif ((float) $r->lectura_medidor > $this->param('lectura_maxima')) {
            $h[] = $this->hallazgo('lectura', self::ALERTA, 'lectura_medidor', 'La lectura del medidor es excesiva.');
        }

        return $h;
    }

    /** @return array<int, array<string, string>> */
    private function fecha(SurveyRegistro $r): array
    {
        $h = [];

        if ($r->fecha_registro === null) {
            return [$this->hallazgo('fecha', self::ERROR, 'fecha_registro', 'Falta la fecha de registro.')];
        }

        if ($r->fecha_registro->isFuture()) {
            $h[] = $this->hallazgo('fecha', self::ERROR, 'fecha_registro', 'La fecha de registro es futura.');
        }

        if ($r->creado_en_origen !== null) {
            if ($r->fecha_registro->gt($r->creado_en_origen)) {
                $h[] = $this->hallazgo('fecha', self::ERROR, 'fecha_registro', 'La fecha de registro es posterior a la de envío.');
            } elseif ($r->fecha_registro->diffInHours($r->creado_en_origen, true) > $this->param('horas_max_entre_registro_y_envio')) {
                $h[] = $this->hallazgo('fecha', self::ALERTA, 'fecha_registro', 'Pasaron más de 24 horas entre el registro de la visita y su envío.');
            }
        }

        return $h;
    }

    /** @return array<int, array<string, string>> */
    private function suspension(SurveyRegistro $r): array
    {
        $h = [];

        if ($r->actividad !== null && $r->actividad !== 'suspension') {
            $h[] = $this->hallazgo('actividad', self::ALERTA, 'actividad', 'La actividad no es una suspensión, está fuera del alcance de esta auditoría.');
        }

        if ($r->suspension_ejecutada === null) {
            return array_merge($h, [$this->hallazgo('suspension', self::ERROR, 'suspension_ejecutada', 'No se indicó si la suspensión se ejecutó.')]);
        }

        if ($r->suspension_ejecutada) {
            if (blank($r->metodo_suspension)) {
                $h[] = $this->hallazgo('suspension', self::ERROR, 'metodo_suspension', 'Falta el método de suspensión instalado.');
            }

            $esSonda = in_array($r->metodo_suspension, ['sonda_ips', 'sonda_cts'], true);

            if ($esSonda) {
                foreach (['serie_sonda' => 'de la sonda', 'serie_caps' => 'del Caps'] as $campo => $etiqueta) {
                    if (blank($r->{$campo})) {
                        $h[] = $this->hallazgo('serie_sonda_caps', self::ERROR, $campo, "Falta la serie {$etiqueta}.");
                    } elseif ((float) $r->{$campo} === 0.0) {
                        $h[] = $this->hallazgo('serie_sonda_caps', self::ERROR, $campo, "La serie {$etiqueta} es cero.");
                    }
                }
            }
        } else {
            if (blank($r->motivo_no_ejecucion)) {
                $h[] = $this->hallazgo('suspension', self::ERROR, 'motivo_no_ejecucion', 'Falta el motivo por el que no se ejecutó la suspensión.');
            }
        }

        return array_merge($h, $this->justificacion($r));
    }

    /** @return array<int, array<string, string>> */
    private function justificacion(SurveyRegistro $r): array
    {
        if (blank($r->justificacion)) {
            return [$this->hallazgo('justificacion', self::ERROR, 'justificacion', 'Falta la justificación.')];
        }

        $texto = Str::lower(Str::ascii($r->justificacion));
        $h = [];

        if (strlen(trim($texto)) < 10) {
            $h[] = $this->hallazgo('justificacion', self::ALERTA, 'justificacion', 'La justificación es demasiado corta.');
        }

        $mencionaSonda = (bool) preg_match('/\b(sonda|cts|ips)\b/', $texto);
        $mencionaTapon = (bool) preg_match('/\b(tapon|taponamiento|acometida)\w*\b/', $texto);
        $esSonda = in_array($r->metodo_suspension, ['sonda_ips', 'sonda_cts'], true);
        $esTapon = in_array($r->metodo_suspension, ['taponamiento_acometida', 'taponamiento_multifamiliar'], true);

        if (($esSonda && $mencionaTapon && ! $mencionaSonda) || ($esTapon && $mencionaSonda)) {
            $h[] = $this->hallazgo('justificacion', self::ALERTA, 'justificacion', 'La justificación describe un método distinto al registrado.');
        }

        return $h;
    }

    /** @return array<int, array<string, string>> */
    private function fotos(SurveyRegistro $r): array
    {
        $h = [];
        $fotos = app(RequisitosDeFotos::class)->evaluar($r);

        foreach ($fotos['requisitos'] as $requisito) {
            if ($requisito['aplica'] && ! $requisito['completa']) {
                $h[] = $this->hallazgo('fotos_minimas', self::ERROR, $requisito['campo'], $requisito['mensaje']);
            }
        }

        if ($fotos['version_anterior'] && $r->suspension_ejecutada === true) {
            $h[] = $this->hallazgo('fotos_minimas', self::ALERTA, 'foto_ivt', 'Se envió con una versión anterior del formulario: no trae la foto del IVT ni las de la actividad por momento.');
        }

        $conHash = $r->adjuntos->whereNotNull('hash');

        foreach ($conHash->groupBy('hash') as $grupo) {
            if ($grupo->count() > 1) {
                $h[] = $this->hallazgo('fotos_repetidas', self::ERROR, 'fotos', 'Hay fotos repetidas dentro del mismo registro: '.$grupo->pluck('nombre')->implode(', ').'.');
            }
        }

        if ($conHash->isNotEmpty()) {
            $usadasEnOtros = SurveyAdjunto::query()
                ->whereIn('hash', $conHash->pluck('hash'))
                ->where('survey_registro_id', '!=', $r->id)
                ->with('registro:id,orden_trabajo')
                ->get();

            foreach ($usadasEnOtros->groupBy('survey_registro_id') as $grupo) {
                $ot = $grupo->first()->registro?->orden_trabajo ?? 'otra visita';
                $h[] = $this->hallazgo('fotos_repetidas', self::ERROR, 'fotos', "Se reutilizó una foto de la visita OT {$ot}.");
            }
        }

        return $h;
    }

    /** @return array<int, array<string, string>> */
    private function ubicacion(SurveyRegistro $r): array
    {
        if ($r->latitud === null || $r->longitud === null) {
            return [$this->hallazgo('ubicacion', self::ERROR, 'ubicacion', 'Falta la ubicación de la visita.')];
        }

        $lat = (float) $r->latitud;
        $lon = (float) $r->longitud;
        $limites = $this->param('limites');

        if (($lat === 0.0 && $lon === 0.0)
            || $lat < $limites['lat_min'] || $lat > $limites['lat_max']
            || $lon < $limites['lon_min'] || $lon > $limites['lon_max']) {
            return [$this->hallazgo('ubicacion', self::ERROR, 'ubicacion', 'La ubicación está fuera de Colombia o es inválida.')];
        }

        $limite = $this->param('distancia_punto_repetido_m');
        $delta = 0.001;
        $h = [];

        $vecinos = $this->otros($r)
            ->whereBetween('latitud', [$lat - $delta, $lat + $delta])
            ->whereBetween('longitud', [$lon - $delta, $lon + $delta])
            ->where('contrato', '!=', $r->contrato)
            ->get(['id', 'contrato', 'orden_trabajo', 'latitud', 'longitud']);

        foreach ($vecinos as $vecino) {
            $metros = Geografia::distanciaMetros($lat, $lon, (float) $vecino->latitud, (float) $vecino->longitud);

            if ($metros < $limite) {
                $h[] = $this->hallazgo('punto_repetido', self::ALERTA, 'ubicacion', 'El punto está a '.round($metros).' m de la visita OT '.$vecino->orden_trabajo.' de otro contrato.');
            }
        }

        return $h;
    }

    /** @return Builder<SurveyRegistro> */
    private function otros(SurveyRegistro $r)
    {
        return SurveyRegistro::query()
            ->withoutGlobalScopes()
            ->where('company_id', $r->company_id)
            ->where('id', '!=', $r->id);
    }

    private function param(string $clave): mixed
    {
        return config("survey_auditoria.parametros.{$clave}");
    }

    /** @return array<string, string> */
    private function hallazgo(string $regla, string $severidad, string $campo, string $mensaje): array
    {
        if (! config("survey_auditoria.reglas.{$regla}", true)) {
            return [];
        }

        $severidad = match ($this->pesos[$regla] ?? AjusteAuditoria::SEGUN_EL_CASO) {
            AjusteAuditoria::NO_APLICAR => null,
            AjusteAuditoria::BLOQUEANTE => self::ERROR,
            AjusteAuditoria::CORREGIBLE => self::ALERTA,
            default => $severidad,
        };

        return $severidad === null ? [] : compact('regla', 'severidad', 'campo', 'mensaje');
    }

    /** Lee una sola vez por empresa lo que ésta configuró para cada regla. */
    private function cargarPesos(?string $companyId): void
    {
        if ($this->pesosDeLaEmpresa === $companyId && $this->pesos !== []) {
            return;
        }

        $this->pesos = AjusteAuditoria::pesosDe($companyId);
        $this->pesosDeLaEmpresa = $companyId;
    }
}
