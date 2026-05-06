<?php

namespace Database\Factories;

use App\Enums\EstadoOrden;
use App\Enums\PrioridadOrden;
use App\Models\OrdenJob;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrdenJob>
 */
class OrdenJobFactory extends Factory
{
    protected $model = OrdenJob::class;

    public function definition(): array
    {
        return [
            'personals_id' => null,
            'contrato' => fake()->numerify('CONT-####'),
            'cliente' => fake()->name(),
            'direccion' => fake()->streetAddress(),
            'barrio' => fake()->word(),
            'medidor' => fake()->numerify('MED-######'),
            'tipo_medidor' => fake()->optional()->randomElement(['digital', 'analógico']),
            'ciclo' => fake()->numerify('C##'),
            'latitud' => fake()->optional()->latitude(),
            'longitud' => fake()->optional()->longitude(),
            'titulo' => fake()->optional()->sentence(),
            'descripcion' => fake()->optional()->paragraph(),
            'prioridad' => fake()->randomElement(PrioridadOrden::cases())->value,
            'estado' => EstadoOrden::Pendiente->value,
            'estado_servicio' => true,
            'fecha_asignacion' => null,
            'fecha_limite' => fake()->optional()->dateTimeBetween('now', '+30 days'),
            'fecha_completado' => null,
        ];
    }
}
