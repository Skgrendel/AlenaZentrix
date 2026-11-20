<?php

namespace App\Services\survey123;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Estado de la sincronización con Survey123 de una empresa: si está en curso, su avance,
 * cómo terminó y cuánto falta para poder lanzarla otra vez.
 */
class EstadoSincronizacion
{
    public const EN_COLA = 'en_cola';

    public const EN_PROCESO = 'en_proceso';

    public const TERMINADO = 'terminado';

    public const ERROR = 'error';

    /** Una corrida que lleva más de esto sin terminar se considera caída y no bloquea nuevas. */
    private const VIGENCIA_MINUTOS = 30;

    /** @return array<string, mixed>|null */
    public function estado(string $companyId): ?array
    {
        return Cache::get($this->clave($companyId));
    }

    public function enCurso(string $companyId): bool
    {
        $estado = $this->estado($companyId);

        return $estado !== null
            && in_array($estado['estado'], [self::EN_COLA, self::EN_PROCESO], true)
            && Carbon::parse($estado['inicio'])->gt(now()->subMinutes(self::VIGENCIA_MINUTOS));
    }

    public function segundosDeEspera(string $companyId): int
    {
        $fin = $this->estado($companyId)['fin'] ?? null;

        if ($fin === null) {
            return 0;
        }

        $libre = Carbon::parse($fin)->addMinutes((int) config('services.survey123.espera_sincronizacion_minutos'));

        return max(0, (int) ceil(now()->diffInSeconds($libre, false)));
    }

    /** Devuelve null si se puede iniciar, o el motivo por el que no. */
    public function iniciar(string $companyId, ?string $usuario = null): ?string
    {
        return Cache::lock($this->clave($companyId).':bloqueo', 10)->block(5, function () use ($companyId, $usuario): ?string {
            if ($this->enCurso($companyId)) {
                return 'Ya hay una sincronización en curso.';
            }

            $espera = $this->segundosDeEspera($companyId);

            if ($espera > 0) {
                return 'Podrás volver a sincronizar en '.ceil($espera / 60).' min.';
            }

            $this->guardar($companyId, [
                'estado' => self::EN_COLA,
                'etapa' => 'En cola',
                'inicio' => now()->toIso8601String(),
                'fin' => null,
                'usuario' => $usuario,
                'nuevos' => 0,
                'actualizados' => 0,
                'sin_cambios' => 0,
                'fotos' => 0,
                'errores' => 0,
                'mensaje' => null,
            ]);

            return null;
        });
    }

    public function enProceso(string $companyId, string $etapa): void
    {
        $this->guardar($companyId, ['estado' => self::EN_PROCESO, 'etapa' => $etapa]);
    }

    /** @param array<string, int> $resumen */
    public function avance(string $companyId, array $resumen, string $etapa): void
    {
        $this->guardar($companyId, ['estado' => self::EN_PROCESO, 'etapa' => $etapa] + $resumen);
    }

    /** @param array<string, int> $resumen */
    public function terminar(string $companyId, array $resumen): void
    {
        $this->guardar($companyId, [
            'estado' => self::TERMINADO,
            'etapa' => 'Terminada',
            'fin' => now()->toIso8601String(),
            'ultima_exitosa' => $this->estado($companyId)['inicio'] ?? now()->toIso8601String(),
        ] + $resumen);
    }

    public function fallar(string $companyId, string $mensaje): void
    {
        $this->guardar($companyId, [
            'estado' => self::ERROR,
            'etapa' => 'Falló',
            'fin' => now()->toIso8601String(),
            'mensaje' => $mensaje,
        ]);
    }

    /** @param array<string, mixed> $cambios */
    private function guardar(string $companyId, array $cambios): void
    {
        Cache::forever($this->clave($companyId), array_merge($this->estado($companyId) ?? [], $cambios));
    }

    private function clave(string $companyId): string
    {
        return "survey123:sincronizacion:{$companyId}";
    }
}
