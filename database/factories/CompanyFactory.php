<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        return [
            'nombre' => fake()->company(),
            'logo' => null,
            'activo' => true,
        ];
    }

    public function inactiva(): static
    {
        return $this->state(['activo' => false]);
    }
}
