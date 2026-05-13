<?php

namespace Database\Factories;

use App\Enums\TipoServicio;
use App\Models\OrdenJob;
use App\Models\reportes;
use App\Models\ubicacion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<reportes>
 */
class ReportesFactory extends Factory
{
    protected $model = reportes::class;

    public function definition(): array
    {
        return [
            'orden_job_id' => OrdenJob::factory()->state(['tipo_servicio' => TipoServicio::Fraudes->value]),
            'ubicacions_id' => ubicacion::factory(),
            'personals_id' => null,
            'numero_orden' => fake()->bothify('ORD-####'),
            'estado' => 'pendiente',
            'revisado' => false,
            'confirmado' => false,
            'hay_fuga_gas' => false,
            'excede_capacidad' => false,
        ];
    }

    public function sinUbicacion(): static
    {
        return $this->state(['ubicacions_id' => null]);
    }

    public function conUbicacionSinCoordenadas(): static
    {
        return $this->state(['ubicacions_id' => ubicacion::factory()->sinCoordenadas()]);
    }

    public function estado(string $estado): static
    {
        return $this->state(['estado' => $estado]);
    }
}
