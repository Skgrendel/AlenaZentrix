<?php

namespace Database\Factories;

use App\Enums\TipoDocumento;
use App\Models\personals;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<personals>
 */
class PersonalsFactory extends Factory
{
    protected $model = personals::class;

    public function definition(): array
    {
        return [
            'tipo_documento' => fake()->randomElement(TipoDocumento::cases())->value,
            'numero_documento' => fake()->unique()->numerify('##########'),
            'nombres' => fake()->firstName(),
            'apellidos' => fake()->lastName(),
            'telefono' => fake()->phoneNumber(),
            'cargo' => fake()->jobTitle(),
        ];
    }
}
