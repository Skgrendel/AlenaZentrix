<?php

namespace App\Services\survey123;

use App\Enums\ResultadoComparacion;
use App\Models\reportes;

/**
 * Contrasta, campo por campo, lo que encontró el auditor en campo con lo que reportó el técnico
 * en Survey123 (Visita de Calidad — Suspensiones).
 *
 * Un campo crítico que no coincide hace que la orden sea "No conforme"; los demás solo generan alerta.
 */
class ComparacionAuditoriaSuspension
{
    public const COINCIDE = 'coincide';

    public const NO_COINCIDE = 'no_coincide';

    public const SIN_DATO = 'sin_dato';

    private const METODOS = [
        'sonda_ips' => 'Sonda (Dispositivo IPS)',
        'sonda_cts' => 'Sonda (Dispositivo CTS)',
        'taponamiento_acometida' => 'Taponamiento de acometida',
        'taponamiento_multifamiliar' => 'Taponamiento Multifamiliar',
    ];

    /**
     * @return array{filas: array<int, array<string, mixed>>, resultado: ?string, alertas: int}
     */
    public function comparar(reportes $reporte): array
    {
        $orden = $reporte->ordenJob;
        $registro = $orden?->surveyRegistro;
        $datos = $reporte->datos_servicio ?? [];

        $ejecutadaAuditor = $this->booleano($datos['suspension_ejecutada'] ?? null);
        $ejecutadaTecnico = $registro?->suspension_ejecutada;
        $metodoAuditor = $datos['metodo_suspension'] ?? null;

        $filas = [];

        if ($ejecutadaTecnico !== null) {
            $filas[] = $this->fila('ejecucion', '¿Suspensión ejecutada?', true, $this->siNo($ejecutadaTecnico), $this->siNo($ejecutadaAuditor), $ejecutadaAuditor === null ? null : $ejecutadaTecnico === $ejecutadaAuditor);
        }

        if ($ejecutadaTecnico === true && $ejecutadaAuditor === true && $registro?->metodo_suspension !== null) {
            $filas[] = $this->fila('metodo', 'Método de suspensión', true, self::METODOS[$registro->metodo_suspension] ?? $registro->metodo_suspension, self::METODOS[$metodoAuditor] ?? $metodoAuditor, $metodoAuditor === null ? null : $registro->metodo_suspension === $metodoAuditor);
        }

        if ($ejecutadaAuditor === true && filled($orden?->serie_medidor_referencia)) {
            $encontrada = $datos['serie_medidor'] ?? null;
            $filas[] = $this->fila('serie_medidor', 'Serie del medidor', true, $orden->serie_medidor_referencia, $encontrada, blank($encontrada) ? null : $this->numero($orden->serie_medidor_referencia) === $this->numero($encontrada));
        }

        if ($ejecutadaAuditor === true && filled($orden?->lectura_referencia)) {
            $encontrada = $datos['lectura_medidor'] ?? null;
            $filas[] = $this->fila('lectura', 'Lectura del medidor', true, $orden->lectura_referencia, $encontrada, blank($encontrada) ? null : $orden->lecturaCoincide($encontrada));
        }

        $filas[] = $this->filaUbicacion($reporte);

        if ($registro?->categoria !== null) {
            $categoria = $datos['categoria_campo'] ?? null;
            $filas[] = $this->fila('categoria', 'Categoría', false, ucfirst($registro->categoria), $categoria ? ucfirst($categoria) : null, $categoria === null ? null : $registro->categoria === $categoria);
        }

        if ($ejecutadaAuditor === true && $registro !== null && in_array($registro->metodo_suspension, ['sonda_ips', 'sonda_cts'], true) && in_array($metodoAuditor, ['sonda_ips', 'sonda_cts'], true)) {
            foreach (['serie_sonda' => 'Serie de la sonda', 'serie_caps' => 'Serie del Caps'] as $campo => $etiqueta) {
                $encontrada = $datos[$campo] ?? null;
                $filas[] = $this->fila($campo, $etiqueta, false, $registro->{$campo}, $encontrada, blank($encontrada) ? null : $this->numero($registro->{$campo}) === $this->numero($encontrada));
            }
        }

        $filas = array_values(array_filter($filas));

        return [
            'filas' => $filas,
            'resultado' => $this->resultadoFinal($filas, $ejecutadaAuditor, $datos['motivo_no_ejecucion'] ?? null)?->value,
            'alertas' => count(array_filter($filas, fn (array $f): bool => ! $f['critico'] && $f['resultado'] !== self::COINCIDE)),
        ];
    }

    public function compararYGuardar(reportes $reporte): reportes
    {
        $comparacion = $this->comparar($reporte);

        $reporte->forceFill([
            'comparacion' => $comparacion,
            'resultado_comparacion' => $comparacion['resultado'],
        ])->saveQuietly();

        return $reporte;
    }

    /** @return array<string, mixed>|null */
    private function filaUbicacion(reportes $reporte): ?array
    {
        $orden = $reporte->ordenJob;

        if ($orden?->latitud_referencia === null || $orden?->longitud_referencia === null) {
            return null;
        }

        $referencia = round((float) $orden->latitud_referencia, 5).', '.round((float) $orden->longitud_referencia, 5);
        $ubicacion = $reporte->ubicacion;

        if ($ubicacion?->latitud === null || $ubicacion?->longitud === null || ! is_numeric($ubicacion->latitud) || ! is_numeric($ubicacion->longitud)) {
            return $this->fila('ubicacion', 'Ubicación de la visita', false, $referencia, null, null);
        }

        $metros = Geografia::distanciaMetros((float) $orden->latitud_referencia, (float) $orden->longitud_referencia, (float) $ubicacion->latitud, (float) $ubicacion->longitud);
        $maximo = (float) config('survey_auditoria.parametros.distancia_auditor_max_m');

        $fila = $this->fila('ubicacion', 'Ubicación de la visita', true, $referencia, round((float) $ubicacion->latitud, 5).', '.round((float) $ubicacion->longitud, 5), $metros <= $maximo);
        $fila['detalle'] = 'A '.round($metros).' m del punto del técnico (máximo '.round($maximo).' m).';

        return $fila;
    }

    /**
     * @param  array<int, array<string, mixed>>  $filas
     */
    private function resultadoFinal(array $filas, ?bool $ejecutadaAuditor, ?string $motivo): ?ResultadoComparacion
    {
        if ($ejecutadaAuditor === false && in_array($motivo, config('survey_auditoria.parametros.motivos_no_verificables'), true)) {
            return ResultadoComparacion::NoVerificable;
        }

        $comparables = array_filter($filas, fn (array $f): bool => in_array($f['resultado'], [self::COINCIDE, self::NO_COINCIDE], true));

        if ($comparables === []) {
            return null;
        }

        $fallaCritica = array_filter($filas, fn (array $f): bool => $f['critico'] && $f['resultado'] === self::NO_COINCIDE);

        return $fallaCritica === [] ? ResultadoComparacion::Conforme : ResultadoComparacion::NoConforme;
    }

    /** @return array<string, mixed> */
    private function fila(string $campo, string $etiqueta, bool $critico, mixed $reportado, mixed $encontrado, ?bool $coincide): array
    {
        return [
            'campo' => $campo,
            'etiqueta' => $etiqueta,
            'critico' => $critico,
            'reportado' => $reportado === null || $reportado === '' ? null : (string) $reportado,
            'encontrado' => $encontrado === null || $encontrado === '' ? null : (string) $encontrado,
            'resultado' => match ($coincide) {
                true => self::COINCIDE,
                false => self::NO_COINCIDE,
                null => self::SIN_DATO,
            },
            'detalle' => null,
        ];
    }

    private function booleano(mixed $valor): ?bool
    {
        return $valor === null || $valor === '' ? null : filter_var($valor, FILTER_VALIDATE_BOOLEAN);
    }

    private function siNo(?bool $valor): ?string
    {
        return $valor === null ? null : ($valor ? 'Sí' : 'No');
    }

    private function numero(mixed $valor): string
    {
        return ltrim(preg_replace('/\D/', '', (string) $valor) ?? '', '0');
    }
}
