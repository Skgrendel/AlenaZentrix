<?php

namespace Database\Factories;

use App\Enums\EstadoSurveyRegistro;
use App\Models\Company;
use App\Models\SurveyRegistro;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<SurveyRegistro>
 */
class SurveyRegistroFactory extends Factory
{
    protected $model = SurveyRegistro::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'object_id' => fake()->unique()->numberBetween(1, 100000),
            'global_id' => fake()->uuid(),
            // Anterior al cambio del formulario (foto del IVT y de la actividad por momento).
            'fecha_registro' => Carbon::parse('2026-11-10 12:00:00', 'UTC'),
            'tecnico' => 'Luis Quiroga',
            'contrato' => fake()->numerify('######'),
            'orden_trabajo' => fake()->numerify('3########'),
            'categoria' => 'residencial',
            'serie_medidor' => fake()->numerify('######'),
            'lectura_medidor' => fake()->numerify('####'),
            'actividad' => 'suspension',
            'suspension_ejecutada' => true,
            'metodo_suspension' => 'taponamiento_acometida',
            'justificacion' => 'Se realiza suspensión desde acometida',
            'latitud' => 10.407333,
            'longitud' => -75.488048,
            'estado' => EstadoSurveyRegistro::Pendiente,
        ];
    }
}
