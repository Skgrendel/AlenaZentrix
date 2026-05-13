<?php

namespace Database\Factories;

use App\Models\ubicacion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ubicacion>
 */
class UbicacionFactory extends Factory
{
    protected $model = ubicacion::class;

    public function definition(): array
    {
        return [
            'direccion' => fake()->streetAddress(),
            'latitud' => (string) fake()->latitude(-4.2, 12.5),
            'longitud' => (string) fake()->longitude(-79.0, -66.8),
        ];
    }

    public function sinCoordenadas(): static
    {
        return $this->state(['latitud' => null, 'longitud' => null]);
    }
}
